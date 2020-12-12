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
    'sid' => env('TWILIO_SID'),
    'token' => env('TWILIO_TOKEN'),
    'from' => env('TWILIO_WA_FROM', '+14155238886')
];
