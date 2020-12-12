<?php


namespace App\BotManDrivers;


use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Attachments\Contact;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class TwilioWhatsAppContactDriver extends TwilioWhatsAppDriver
{
    const DRIVER_NAME = 'TwilioWhatsAppContact';

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
        return $this->event->get('AccountSid') && $this->event->get('NumMedia') > 0 && $this->event->get('MediaContentType0') == 'text/vcard';
    }

    /**
     * Retrieve the chat message.
     *
     * @return array|IncomingMessage[]
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $data = file_get_contents($this->event->get('MediaUrl0'));
            $vcard = \Sabre\VObject\Reader::read($data, \Sabre\VObject\Reader::OPTION_FORGIVING);
            $first_name = (string)$vcard->FN;
            /** @var \Sabre\VObject\Property\FlatText $tel */
            $tel = $vcard->TEL;
            $phone_number = (string)($vcard->TEL[0] ?? $vcard->TEL);
            if($phone_number){
                $phone_number = strtr($phone_number, [' '=> '']);
            }
            $names = explode(' ', $first_name, 2);
            $first_name = $names[0];
            $last_name = $names[1] ?? '';

            $message = new IncomingMessage(Contact::PATTERN, $this->event->get('From'), $this->event->get('To'), $this->event);
            $contact = new Contact(
                $phone_number,
                $first_name,
                $last_name,
                '',
                $data
            );
            $message->setContact($contact);
            //logger()->channel('papertrail')->alert('contact seen', ['contact' => $contact->toWebDriver()]);


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