<?php


namespace App\BotManDrivers;


use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Attachments\Location;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class TwilioWhatsAppLocationDriver extends TwilioWhatsAppDriver
{
    const DRIVER_NAME = 'TwilioWhatsAppLocation';

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
        return $this->event->get('AccountSid') && !(is_null($this->event->get('Latitude')) || is_null($this->event->get('Longitude')));
    }

    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = new IncomingMessage(Location::PATTERN, $this->event->get('From'), $this->event->get('To'), $this->event);
            $location = new Location(
                $this->event->get('Latitude'),
                $this->event->get('Longitude')
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