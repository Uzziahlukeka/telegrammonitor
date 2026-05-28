<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\Facades;

use Illuminate\Support\Facades\Facade;
use Uzhlaravel\Telegramlogs\Support\SupportBotHandler;

/**
 * @see \Uzhlaravel\Telegramlogs\Support\SupportBotHandler
 */
class TelegramSupport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SupportBotHandler::class;
    }
}
