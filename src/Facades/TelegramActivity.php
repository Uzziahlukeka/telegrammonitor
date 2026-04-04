<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\Facades;

use Illuminate\Support\Facades\Facade;
use Uzhlaravel\Telegramlogs\ActivityLogger;

/**
 * Fluent facade for sending activity notifications to Telegram.
 *
 * @method static ActivityLogger performedOn(\Illuminate\Database\Eloquent\Model $model)
 * @method static ActivityLogger causedBy(mixed $causer)
 * @method static ActivityLogger withProperties(array $properties)
 * @method static ActivityLogger withProperty(string $key, mixed $value)
 * @method static ActivityLogger event(string $event)
 * @method static bool dispatch(string $description = '')
 * @method static bool log(string $description)
 *
 * @see ActivityLogger
 */
final class TelegramActivity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogger::class;
    }
}
