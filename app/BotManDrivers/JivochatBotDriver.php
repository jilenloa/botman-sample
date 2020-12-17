<?php


namespace App\BotManDrivers;


use BotMan\BotMan\Drivers\Events\GenericEvent;
use BotMan\BotMan\Interfaces\DriverInterface;
use BotMan\BotMan\Interfaces\UserInterface;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Users\User;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Rest\Client;


class JivochatBotDriver extends \BotMan\BotMan\Drivers\HttpDriver
{
    const DRIVER_NAME = 'JivochatBot';

    /** @var string messages would be sent to jivo using this endpoint */
    const JIVO_ENDPOINT = 'https://bot.jivosite.com/webhooks/{provider_id}';

    const ERROR_INVALID_CLIENT = 'invalid_client';
    const ERROR_UNAUTHORIZED_CLIENT = 'unauthorized_client';
    const ERROR_INVALID_REQUEST = 'invalid_request';

    const EVENT_CLIENT_MESSAGE = 'CLIENT_MESSAGE'; //handle this event
    const EVENT_BOT_MESSAGE = 'BOT_MESSAGE';
    const EVENT_INVITE_AGENT = 'INVITE_AGENT';
    const EVENT_AGENT_JOINED = 'AGENT_JOINED'; //handle this event
    const EVENT_AGENT_UNAVAILABLE = 'AGENT_UNAVAILABLE';//handle this event

    /**
     * @var IncomingMessage[]
     */
    protected $messages;

    /** @var string */
    protected $requestUri;

    /**
     * @param $code
     * @param $message
     * @return array
     */
    private function prepareErrorResponseBody($code, $message){
        return [
            'error' => compact('code', 'message')
        ];
    }


    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = new IncomingMessage($this->event->get('text'), $this->event->get('address'), $this->event->get('address'), $this->event);
            $this->messages = [$message];
        }
        return $this->messages;
    }

    public function hasMatchingEvent()
    {
        if($this->event->has('event')){
            $event = new GenericEvent($this->event);
            $event->setName($this->event->has('event'));
            return $event;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isConfigured()
    {
        return true;
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        // this function can be just like the other drivers
        try{
            $user = \App\User::findUserAccount(null, $matchingMessage->getSender());
            if($user){
                $user_info = $user->jsonSerialize();
            }else{
                $user_info = ['phone_number' => $matchingMessage->getSender()];
            }
            return new User($matchingMessage->getSender(),
                $user->name ?? null,
                null, $user->email ?? null, $user_info);
        }catch (\Exception $exception){
            return new User($matchingMessage->getSender(),
                null,
                null, null, []);
        }

    }

    /**
     * @inheritDoc
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $parameters = $additionalParameters;

        if ($message instanceof Question) {
            $text = $message->getText();
            $parameters['buttons'] = $message->getButtons() ?? [];
            $parameters['event'] = self::EVENT_CLIENT_MESSAGE;
        } elseif ($message instanceof OutgoingMessage) {
            $text = $message->getText();

            if ($message->getAttachment() !== null) {
                $attachment = $message->getAttachment();
                if($attachment instanceof Contact){
                    $contact_name = implode(' ', array_filter([$attachment->getFirstName(), $attachment->getLastName()]));
                    $parameters['contact'] = [$attachment->getPhoneNumber(), $contact_name];
                }elseif($attachment instanceof Location){
                    $location_name = $text;
                    if(isset($parameters['title'])){
                        $location_name = $parameters['title'];
                    }elseif(isset($parameters['address'])){
                        $location_name = $parameters['address'];
                    }
                    $parameters['location'] = [$attachment->getLatitude(), $attachment->getLongitude(), $location_name];
                }elseif($attachment instanceof File){
                    $caption = $attachment->getPayload()['caption'] ?? $text;
                    $name = $attachment->getPayload()['name'] ?? 'file';
                    $parameters['file'] = [
                        'fileUrl' => $attachment->getUrl(),
                        'name' => $name,
                        'caption' => $caption,
                        'contentType' => 'document'
                    ];
                }elseif($attachment instanceof Video){
                    $caption = $attachment->getPayload()['caption'] ?? $text;
                    $name = $attachment->getPayload()['name'] ?? 'video';
                    $parameters['file'] = [
                        'fileUrl' => $attachment->getUrl(),
                        'name' => $name,
                        'caption' => $caption,
                        'contentType' => 'video'
                    ];
                }elseif($attachment instanceof Audio){
                    $caption = $attachment->getPayload()['caption'] ?? $text;
                    $name = $attachment->getPayload()['name'] ?? 'audio';
                    $parameters['file'] = [
                        'fileUrl' => $attachment->getUrl(),
                        'name' => $name,
                        'caption' => $caption
                    ];
                }elseif($attachment instanceof Image){
                    $caption = $text;
                    if($attachment->getTitle()){
                        $caption = $attachment->getTitle();
                    }
                    $name = $attachment->getPayload()['name'] ?? 'image';
                    $parameters['file'] = [
                        'fileUrl' => $attachment->getUrl(),
                        'name' => $name,
                        'caption' => $caption,
                        'contentType' => 'image'
                    ];
                }

            }

        } else {
            $text = $message;
        }

        $parameters['message'] = $text;
        if(isset($parameters['buttons']) && $parameters['buttons']){
            $parameters['message'] .= "\n";
            foreach((array)$parameters['buttons'] as $menu_item){
                $parameters['message'] .= "{$menu_item['value']}. {$menu_item['text']}\n";
            }
        }

        $parameters['to'] = $matchingMessage->getSender();
        $parameters['from'] = $matchingMessage->getRecipient();

        unset($parameters['buttons']);

        return $parameters;
    }

    /**
     * @inheritDoc
     */
    public function sendPayload($payload)
    {
        $payload_to_send = [
            'id' => Uuid::uuid4()->toString(),
            'event' => $payload['event']
        ];

        if(isset($payload['event']) && $payload['event'] && in_array($payload['event'], [self::EVENT_INVITE_AGENT])){
            $payload_to_send['client_id'] = $payload['client_id'];
            $payload_to_send['chat_id'] = $payload['chat_id'];
        }else{

        }

        if(isset($payload['message'])){
            $payload_to_send['text'] = $payload['message'];
        }

        if(isset($payload['location'])){
            $payload_to_send['latitude'] = $payload['location'][0];
            $payload_to_send['longitude'] = $payload['location'][1];
            $payload_to_send['contentType'] = 'location';

            if(isset($payload['location'][2]) && $payload['location'][2]){
                $payload_to_send['caption'] = $payload['location'][2];
            }
        }

        if(isset($payload['file'])){
            if(isset($payload['file']['caption']) && $payload['file']['caption']){
                $payload_to_send['attachmentName'] = $payload['file']['caption'];
            }
            $payload_to_send['attachmentUrl'] = $payload['file']['fileUrl'];
            $payload_to_send['contentType'] = $payload['file']['contentType'];
        }

        $request = $this->http->post(self::API.'/imOutMessage', [], $payload_to_send, [
            'Content-Type: application/json',
            'X-API-KEY: '.$this->config->get('apiKey'),
            'Accept: */*'
        ], true);

        logger()->alert('response from mfms', ['response' => $request->getContent(), 'payload' => $payload_to_send]);

        return $request;
    }

    /**
     * @inheritDoc
     */
    public function buildPayload(Request $request)
    {
        $input = file_get_contents('php://input');
        $webhookRequest = json_decode($input, false);

        logger()->alert('incoming jivo bot chat', ['input'=>$webhookRequest, 'class' =>get_class($this)]);

        $this->payload = $webhookRequest;
        $this->requestUri = $request->getUri();
        $this->event = \Illuminate\Support\Collection::make($this->payload);
        $this->config = \Illuminate\Support\Collection::make($this->config->get('mfms_whatsapp', []));
    }

    /**
     * @inheritDoc
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        // TODO: Implement sendRequest() method.
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return $this->event->has('apiKeyId') && $this->event->get('apiKeyId') && $this->event->get('imType') == 'whatsapp';
    }

    /**
     * @param  IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())
            ->setValue($message->getText())
            ->setInteractiveReply(true)
            ->setMessage($message);
    }
}