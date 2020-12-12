<?php

namespace App\Conversations;

use BotMan\BotMan\Messages\Attachments\Location;
use Illuminate\Foundation\Inspiring;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;

class CalculateDeliveryConversation extends Conversation
{
    /**
     * First question
     */
    public function askLocation()
    {
        $question = Question::create("Send us the location where you want delivery to")
            ->fallback('Unable to ask question')
            ;

        return $this->askForLocation($question, function ($location) {
            /**@var Location $location */
            $this->say("You said you are at {$location->getLatitude()},{$location->getLongitude()}");
        });
    }

    /**
     * Start the conversation
     */
    public function run()
    {
        $this->askLocation();
    }
}
