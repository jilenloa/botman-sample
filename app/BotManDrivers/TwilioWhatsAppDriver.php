<?php


namespace App\BotManDrivers;


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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Rest\Client;


class TwilioWhatsAppDriver extends \BotMan\BotMan\Drivers\HttpDriver
{
    const DRIVER_NAME = 'TwilioWhatsApp';

    /**
     * @var IncomingMessage[]
     */
    protected $messages;

    /** @var string */
    protected $requestUri;


    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = new IncomingMessage($this->event->get('Body'), $this->event->get('From'), $this->event->get('To'), $this->event);
            $this->messages = [$message];
        }
        return $this->messages;
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
                        'caption' => $caption
                    ];
                }elseif($attachment instanceof Video){
                    $caption = $attachment->getPayload()['caption'] ?? $text;
                    $name = $attachment->getPayload()['name'] ?? 'video';
                    $parameters['file'] = [
                        'fileUrl' => $attachment->getUrl(),
                        'name' => $name,
                        'caption' => $caption
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
                        'caption' => $caption
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

        $parameters['sid'] = $this->config->get('sid');
        $parameters['token'] = $this->config->get('token');
        $parameters['to'] = "+".$matchingMessage->getSender();
        $parameters['from'] = "+".$matchingMessage->getRecipient();

        unset($parameters['buttons']);

        return $parameters;
    }

    /**
     * @inheritDoc
     */
    public function sendPayload($payload)
    {
        $sid    = $payload['sid'];
        $token  = $payload['token'];
        $twilio = new Client($sid, $token);

        $body = $payload['message'];
        $custom_payload = [];
        if(isset($payload['location'])){
            if(isset($payload['location'][2]) && $payload['location'][2]){
                $custom_payload['persistentAction'] = ["geo:{$payload['location'][0]},{$payload['location'][1]}|{$payload['location'][2]}"];
            }else{
                $custom_payload['persistentAction'] = ["geo:{$payload['location'][0]},{$payload['location'][1]}"];
            }
        }

        if(isset($payload['file'])){
            $custom_payload['mediaUrl'] = $payload['file']['fileUrl'];
        }

        $twilio_options = array_merge(array(
            "from" => "whatsapp:{$this->config->get('from')}",
            "body" => $body
        ), $custom_payload);

        $message = $twilio->messages
            ->create("whatsapp:{$payload['to']}", // to
                $twilio_options
            );
        $statusCode = $message->sid ? 200 : 500;

        return new Response(json_encode($message), $statusCode, []);

    }

    /**
     * @inheritDoc
     */
    public function buildPayload(Request $request)
    {
        $webhookRequest = (object)[
            'AccountSid' => $request->get('AccountSid'),
            'Body' => $request->get('Body'),
            'From' => $request->get('From'),
            'To' => $request->get('To'),
            'Latitude' => $request->get('Latitude'),
            'Longitude' => $request->get('Longitude'),
            'NumMedia' => $request->get('NumMedia'),
            'MediaContentType0' => $request->get('MediaContentType0'),
            'MediaUrl0' => $request->get('MediaUrl0'),
        ];

        logger()->alert('incoming wasms chat', ['input'=>$webhookRequest, 'class' =>get_class($this)]);

        if($webhookRequest->From){
            $webhookRequest->From = substr($request->get('From'), 10);
        }
        if($webhookRequest->To){
            $webhookRequest->To = substr($request->get('To'), 10);
        }

        $this->payload = $webhookRequest;
        $this->requestUri = $request->getUri();
        $this->event = \Illuminate\Support\Collection::make($this->payload);
        $this->config = \Illuminate\Support\Collection::make($this->config->get('twilio_whatsapp', []));

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
        return (bool)$this->event->get('AccountSid');
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