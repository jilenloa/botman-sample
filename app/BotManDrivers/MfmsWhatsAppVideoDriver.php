<?php


namespace App\BotManDrivers;


use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class MfmsWhatsAppVideoDriver extends MfmsWhatsAppDriver
{
    const DRIVER_NAME = 'MfmsWhatsAppVideo';

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
        return $this->event->has('apiKeyId') && $this->event->get('contentType') == 'video';
    }

    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = new IncomingMessage(Video::PATTERN, $this->event->get('address'), $this->event->get('address'), $this->event);
            $attachment = new Video($this->event->get('attachmentUrl'));
            $message->setVideos([$attachment]);
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