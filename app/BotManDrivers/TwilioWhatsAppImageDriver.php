<?php


namespace App\BotManDrivers;


use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class TwilioWhatsAppImageDriver extends TwilioWhatsAppDriver
{
    const DRIVER_NAME = 'TwilioWhatsAppImage';

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return true;
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return $this->event->get('AccountSid') && $this->event->get('NumMedia') > 0 && in_array($this->event->get('MediaContentType0'), ['image/png', 'image/jpeg']);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = new IncomingMessage(Image::PATTERN, $this->event->get('From'), $this->event->get('To'), $this->event);
            $attachment = new Image($this->event->get('MediaUrl0'));
            $message->setImages([$attachment]);
            $this->messages = [$message];
        }

        return $this->messages;
    }

    /**
     * @return bool|DriverEventInterface
     */
    public function hasMatchingEvent()
    {
        return false;
    }
}