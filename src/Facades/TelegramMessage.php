<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\Facades;

use Illuminate\Support\Facades\Facade;

final class TelegramMessage extends Facade
{
    /**
     * @method static array|bool message(string $text)
     * @method static array|bool send(string $text, array $options = [])
     * @method static array|bool toChat(string $chatId, string $text, array $options = [])
     * @method static array|bool test()
     * @method static array|bool getBotInfo()
     *
     * @see \Uzziahlukeka\TelegramMonitor\TelegramMessage
     */
    protected static function getFacadeAccessor()
    {
        return 'telegram-message';
    }
}
