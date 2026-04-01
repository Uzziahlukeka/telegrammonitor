<?php

namespace Uzhlaravel\Telegramlogs\Facades;

use Illuminate\Support\Facades\Facade;
use Uzhlaravel\Telegramlogs\ActivityLogger;

/**
 * Fluent facade for sending activity notifications to Telegram.
 *
 * @method static \Uzhlaravel\Telegramlogs\ActivityLogger performedOn(\Illuminate\Database\Eloquent\Model $model)
 * @method static \Uzhlaravel\Telegramlogs\ActivityLogger causedBy(mixed $causer)
 * @method static \Uzhlaravel\Telegramlogs\ActivityLogger withProperties(array $properties)
 * @method static \Uzhlaravel\Telegramlogs\ActivityLogger withProperty(string $key, mixed $value)
 * @method static \Uzhlaravel\Telegramlogs\ActivityLogger event(string $event)
 * @method static bool dispatch(string $description = '')
 * @method static bool log(string $description)
 *
 * @see \Uzhlaravel\Telegramlogs\ActivityLogger
 */
class TelegramActivity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogger::class;
    }
}
