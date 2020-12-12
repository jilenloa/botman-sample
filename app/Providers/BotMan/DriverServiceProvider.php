<?php

namespace App\Providers\BotMan;

use App\BotManDrivers\MfmsWhatsAppDriver;
use App\BotManDrivers\MfmsWhatsAppLocationDriver;
use App\BotManDrivers\TwilioWhatsAppAudioDriver;
use App\BotManDrivers\TwilioWhatsAppContactDriver;
use App\BotManDrivers\TwilioWhatsAppDriver;
use App\BotManDrivers\TwilioWhatsAppFileDriver;
use App\BotManDrivers\TwilioWhatsAppImageDriver;
use App\BotManDrivers\TwilioWhatsAppLocationDriver;
use App\BotManDrivers\TwilioWhatsAppVideoDriver;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Web\WebDriver;
use BotMan\Studio\Providers\DriverServiceProvider as ServiceProvider;

class DriverServiceProvider extends ServiceProvider
{
    /**
     * The drivers that should be loaded to
     * use with BotMan
     *
     * @var array
     */
    protected $drivers = [
        MfmsWhatsAppDriver::class,
        MfmsWhatsAppLocationDriver::class,

        TwilioWhatsAppDriver::class,
        TwilioWhatsAppVideoDriver::class,
        TwilioWhatsAppContactDriver::class,
        TwilioWhatsAppLocationDriver::class,
        TwilioWhatsAppImageDriver::class,
        TwilioWhatsAppFileDriver::class,
        TwilioWhatsAppAudioDriver::class,
        WebDriver::class
    ];

    /**
     * @return void
     */
    public function boot()
    {
        parent::boot();

        foreach ($this->drivers as $driver) {
            DriverManager::loadDriver($driver);
        }
    }
}
