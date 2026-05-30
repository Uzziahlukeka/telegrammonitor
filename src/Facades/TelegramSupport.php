<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\Facades;

use Illuminate\Support\Facades\Facade;
use Uzziahlukeka\TelegramMonitor\Support\SupportBotHandler;

/**
 * @see SupportBotHandler
 */
final class TelegramSupport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SupportBotHandler::class;
    }
}
