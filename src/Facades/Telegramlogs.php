<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\Facades;

use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Facade;

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
 *
 * @see LogManager
 */
final class Telegramlogs extends Facade
{
    /**
     * Override the __callStatic to route calls to telegram channel
     */
    public static function __callStatic($method, $args)
    {
        return self::channel()->$method(...$args);
    }

    /**
     * Get the telegram logger instance
     */
    public static function channel()
    {
        return self::getFacadeRoot()->channel('telegram');
    }

    protected static function getFacadeAccessor(): string
    {
        return 'log';
    }
}
