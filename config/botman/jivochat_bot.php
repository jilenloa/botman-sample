<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web verification
    |--------------------------------------------------------------------------
    |
    | This array will be used to match incoming HTTP requests against your
    | web endpoint, to see if the request should match the web driver.
    |
    */
    'provider_id' => env('JIVOCHAT_BOT_PROVIDER_ID'),
    'token' => env('JIVOCHAT_BOT_TOKEN'), //this is issued by you and given to Jivo
];
