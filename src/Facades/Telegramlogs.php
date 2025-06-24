<?php

namespace Uzhlaravel\Telegramlogs\Facades;

use Illuminate\Support\Facades\Facade;
use Monolog\Logger;
use Uzhlaravel\Telegramlogs\Telegramlogs as TelegramLoggerInstance;

/**
 * @method static void emergency(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static void log($level, string $message, array $context = [])
 * @method static Logger channel(string $channel = null)
 * @method static TelegramLoggerInstance setBotToken(string $token)
 * @method static TelegramLoggerInstance setChatId(string $chatId)
 * @method static TelegramLoggerInstance setTopicId(?string $topicId)
 * @method static TelegramLoggerInstance setTimeout(int $timeout)
 *
 * @see \Uzhlaravel\Telegramlogs\Telegramlogs
 */
class Telegramlogs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Uzhlaravel\Telegramlogs\Telegramlogs::class;
    }
}
