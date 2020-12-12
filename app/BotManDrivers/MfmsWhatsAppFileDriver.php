<?php


namespace App\BotManDrivers;


use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class MfmsWhatsAppFileDriver extends MfmsWhatsAppDriver
{
    const DRIVER_NAME = 'MfmsWhatsAppFile';

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
        return $this->event->has('apiKeyId') && $this->event->get('contentType') == 'document';
    }

    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = new IncomingMessage(File::PATTERN, $this->event->get('address'), $this->event->get('address'), $this->event);
            $attachment = new File($this->event->get('attachmentUrl'));
            $message->setFiles([$attachment]);
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