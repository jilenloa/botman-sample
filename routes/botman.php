<?php
use App\Http\Controllers\BotManController;

/** @var \BotMan\BotMan\BotMan $botman */
$botman = resolve('botman');

$botman->hears('Hi', function ($bot) {
    $bot->reply('Hello!');
});
$botman->hears('Start conversation', BotManController::class.'@startConversation');
$botman->hears('delivery', BotManController::class.'@startConversation2');

//$botman->on()
