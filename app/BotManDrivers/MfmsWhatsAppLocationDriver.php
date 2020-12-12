<?php


namespace App\BotManDrivers;


use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class MfmsWhatsAppLocationDriver extends MfmsWhatsAppDriver
{
    const DRIVER_NAME = 'MfmsWhatsAppLocation';

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
        return $this->event->has('apiKeyId') && !(is_null($this->event->get('latitude')) || is_null($this->event->get('longitude')));
    }

    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = new IncomingMessage(Location::PATTERN, $this->event->get('address'), $this->event->get('address'), $this->event);
            $location = new Location(
                $this->event->get('latitude'),
                $this->event->get('longitude')
            );
            $message->setLocation($location);

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