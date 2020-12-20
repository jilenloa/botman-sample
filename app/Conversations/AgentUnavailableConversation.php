<?php


namespace App\Conversations;


use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;

class AgentUnavailableConversation extends Conversation
{
    protected $phone_number;

    public function run()
    {
        $this->askPhone();
    }

    public function askPhone(){
        $prompt = Question::create('Our agents are currently available. Please leave your phone number with us so that we can reach you as soon as possible.');
        $this->ask($prompt, function($answer){
            /**@var Answer $answer */
            $this->phone_number = $answer->getText();
            $this->say("Thank you for reaching out. We would call you on {$this->phone_number}.");
        });
    }
}