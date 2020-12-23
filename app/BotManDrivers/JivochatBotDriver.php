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
use League\HTMLToMarkdown\HtmlConverter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Rest\Client;


class JivochatBotDriver extends \BotMan\BotMan\Drivers\HttpDriver
{
    const DRIVER_NAME = 'JivochatBot';

    /** @var string messages would be sent to jivo using this endpoint */
    const JIVO_TOKEN_ENDPOINT = 'https://bot.jivosite.com/webhooks/{provider_id}/{token}';

    const ERROR_INVALID_CLIENT = 'invalid_client';
    const ERROR_UNAUTHORIZED_CLIENT = 'unauthorized_client';
    const ERROR_INVALID_REQUEST = 'invalid_request';

    const EVENT_CLIENT_MESSAGE = 'CLIENT_MESSAGE'; //handle this event
    const EVENT_BOT_MESSAGE = 'BOT_MESSAGE';
    const EVENT_INVITE_AGENT = 'INVITE_AGENT';
    const EVENT_AGENT_JOINED = 'AGENT_JOINED'; //handle this event
    const EVENT_AGENT_UNAVAILABLE = 'AGENT_UNAVAILABLE';//handle this event
    const EVENT_ERROR = 'ERROR';//handle this event

    protected $authenticated;

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
    public static function prepareErrorResponseBody($code, $message){
        return [
            'error' => compact('code', 'message')
        ];
    }

    public static function sendError($payload){
        (new Response(json_encode($payload->except('http_status')->toArray()), $payload['http_status'] ?? 401, ['Content-Type' => 'application/json']))->send();
    }


    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            if($this->authenticated){
                $_msg = (array)$this->event->get('message', []);
                $text = $_msg['text'] ?? '';
                if(isset($_msg['button_id'])){
                    $text = $_msg['button_id'];
                }
            }else{
                $text = '';
            }
            $message = new IncomingMessage($text, $this->event->get('chat_id'), $this->event->get('chat_id'), $this->event);
            $this->messages = [$message];
        }
        return $this->messages;
    }

    public function hasMatchingEvent()
    {
        if(!$this->authenticated){
            $event = new GenericEvent(collect(['code' => self::ERROR_INVALID_CLIENT, 'message' => "Authentication failed", 'http_status' => 401]));
            $event->setName(self::EVENT_ERROR);
            return $event;
        } else if($this->event->has('event') && $this->event->get('event') != self::EVENT_CLIENT_MESSAGE){
            $event = new GenericEvent($this->event);
            $event->setName($this->event->get('event'));
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
        if($text == self::EVENT_INVITE_AGENT){
            $parameters['event'] = self::EVENT_INVITE_AGENT;
        }
        $parameters['message_nobuttons'] = $text;
        $formatted_buttons  = [];
        if(isset($parameters['buttons']) && $parameters['buttons']){
            if(count($parameters['buttons']) > 3){
                $parameters['message'] .= "\n";
                $formatted_buttons = [];
                foreach((array)$parameters['buttons'] as $menu_item){
                    $parameters['message'] .= "{$menu_item['value']}. {$menu_item['text']}\n";
                }
            }else{
                $parameters['message_nobuttons'] .= "\n";
                foreach((array)$parameters['buttons'] as $menu_item){
                    $parameters['message_nobuttons'] .= "{$menu_item['value']}. {$menu_item['text']}\n";
                    $formatted_buttons[] = [
                        'text' => $menu_item['text'],
                        'id' => $menu_item['value'],
                    ];
                }
            }
        }

        $parameters['to'] = $matchingMessage->getSender();
        $parameters['from'] = $matchingMessage->getRecipient();

        $parameters['client_id'] = $matchingMessage->getPayload()->get('client_id');
        $parameters['chat_id'] = $matchingMessage->getPayload()->get('chat_id');

        $parameters['buttons'] = $formatted_buttons;

        return $parameters;
    }

    /**
     * @inheritDoc
     */
    public function sendPayload($payload)
    {
        $payload_to_send = [
            'id' => Uuid::uuid4()->toString(),
            'event' => $payload['event'] ?? self::EVENT_BOT_MESSAGE
        ];

        if(isset($payload['event']) && $payload['event'] && in_array($payload['event'], [self::EVENT_INVITE_AGENT])){
            $payload_to_send['client_id'] = $payload['client_id'];
            $payload_to_send['chat_id'] = $payload['chat_id'];
        }

        if($payload_to_send['event'] == self::EVENT_BOT_MESSAGE){
            $payload_to_send['message'] = [
                'text' => $payload['message'] ?? ''
            ];

            if(isset($payload['buttons']) && $payload['buttons']){
                $payload_to_send['message']['type'] = 'BUTTONS';
                $payload_to_send['message']['title'] = $payload['message'];
                $payload_to_send['message']['text'] = $payload['message_nobuttons'];
            }elseif(isset($payload['type']) && strtolower($payload['type']) == 'markdown'){
                $payload_to_send['message']['type'] = 'MARKDOWN';
                $converter = new HtmlConverter();
                $markdown = $converter->convert($payload['message']);

                $payload_to_send['message']['content'] = $markdown;
                $payload_to_send['message']['text'] = strip_tags($payload['message']);
            }else{
                $payload_to_send['message']['type'] = 'TEXT';
            }

            if(isset($payload['location'])){
                if(isset($payload['location'][2]) && $payload['location'][2]){
                    $payload_to_send['message']['text'] .= sprintf("Location: %s,%s (%s)", $payload['location'][0], $payload['location'][1], $payload['location'][2]);
                }else{
                    $payload_to_send['message']['text'] .= sprintf("Location: %s,%s", $payload['location'][0], $payload['location'][1]);
                }
            }

            if(isset($payload['contact'])){
                $payload_to_send['message']['text'] .= sprintf("Contact: %s (%s)", $payload['contact'][0], $payload['contact'][1]);
            }

            if(isset($payload['file'])){
                if(isset($payload['file']['caption']) && $payload['file']['caption']){
                    $payload_to_send['message']['text'] .= sprintf("File: %s (%s)", $payload['file']['fileUrl'], $payload['file']['caption']);
                }else{
                    $payload_to_send['message']['text'] .= sprintf("File: %s", $payload['file']['fileUrl']);
                }
            }
        }

        if(isset($payload['buttons']) && $payload['buttons']){
            $payload_to_send['buttons'] = $payload['buttons'];
        }

        $url = strtr(self::JIVO_TOKEN_ENDPOINT, [
            '{provider_id}' => $this->config->get('provider_id'),
            '{token}' => $this->config->get('token')
        ]);

        if($this->config->get('live')){
            $request = $this->http->post($url, [], $payload_to_send, [
                'Content-Type: application/json',
                'Accept: */*'
            ], true);

            logger()->alert('response from jivo', ['response' => $request->getContent(), 'payload' => $payload_to_send]);
            return $request;
        }else{
            (new Response(json_encode($payload_to_send), 200, ['Content-Type' => 'application/json']))->send();
        }
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
        $this->config = \Illuminate\Support\Collection::make($this->config->get('jivochat_bot', []));

        $this->authenticated = $this->config->get('token') == basename($request->getPathInfo());
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
        return $this->event->has('event') && $this->event->get('id');
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