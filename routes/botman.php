<?php

use App\Conversations\AgentUnavailableConversation;
use App\Http\Controllers\BotManController;
use App\BotManDrivers\JivochatBotDriver;

/** @var \BotMan\BotMan\BotMan $botman */
$botman = resolve('botman');

$botman->hears('Hi', function ($bot) {
    $bot->reply('Hello!');
});
$botman->hears('Start conversation', BotManController::class.'@startConversation');
$botman->hears('delivery', BotManController::class.'@startConversation2');

$botman->on(JivochatBotDriver::EVENT_ERROR, function($payload, $bot){
    JivochatBotDriver::sendError($payload);
});

$botman->on(JivochatBotDriver::EVENT_AGENT_UNAVAILABLE, function($payload, $bot){
    $bot->startConversation(new AgentUnavailableConversation());
});

$botman->on(JivochatBotDriver::EVENT_AGENT_JOINED, function($payload, $bot){
    logger()->alert('agent joined');
    $bot->reply('ok');
});

$botman->fallback(function($bot){
    $bot->reply(JivochatBotDriver::EVENT_INVITE_AGENT);
});