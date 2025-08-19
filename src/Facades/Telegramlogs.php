<?php

namespace Uzhlaravel\Telegramlogs\Facades;

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
 * @see \Illuminate\Log\LogManager
 */
class Telegramlogs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'log';
    }

    /**
     * Get the telegram logger instance
     */
    public static function channel()
    {
        return static::getFacadeRoot()->channel('telegram');
    }

    /**
     * Override the __callStatic to route calls to telegram channel
     */
    public static function __callStatic($method, $args)
    {
        return static::channel()->$method(...$args);
    }
}
