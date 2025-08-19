<?php

namespace Uzhlaravel\Telegramlogs\Facades;

use Illuminate\Support\Facades\Facade;

class TelegramMessage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'telegram-message';
    }
}
