<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\Traits;

use Uzhlaravel\Telegramlogs\ActivityLogger;

/**
 * Add this trait to any Eloquent model to automatically send
 * activity notifications to Telegram on create / update / delete events.
 *
 * Example:
 *
 *   use Uzhlaravel\Telegramlogs\Traits\HasTelegramActivity;
 *
 *   class Post extends Model
 *   {
 *       use HasTelegramActivity;
 *
 *       // Optional: customise which events to track for this model only
 *       protected array $telegramActivityEvents = ['created', 'deleted'];
 *
 *       // Optional: customise the description sent to Telegram
 *       public function getTelegramActivityDescription(string $event): string
 *       {
 *           return ucfirst($event) . ' post: ' . $this->title;
 *       }
 *
 *       // Optional: attach extra properties to the notification
 *       public function getTelegramActivityProperties(string $event): array
 *       {
 *           return ['slug' => $this->slug, 'author' => $this->user->name];
 *       }
 *   }
 */
trait HasTelegramActivity
{
    /**
     * Boot the trait: register Eloquent event listeners.
     */
    public static function bootHasTelegramActivity(): void
    {
        foreach (static::resolvedTelegramActivityEvents() as $event) {
            static::$event(function (self $model) use ($event): void {
                $model->fireTelegramActivity($event);
            });
        }
    }

    /**
     * Customise the activity description. Override in your model.
     */
    public function getTelegramActivityDescription(string $event): string
    {
        return ucfirst($event).' '.class_basename($this);
    }

    /**
     * Attach extra properties. Override in your model.
     *
     * @return array<string, mixed>
     */
    public function getTelegramActivityProperties(string $event): array
    {
        return [];
    }

    /**
     * Determine which events to track for this model.
     * Model-level $telegramActivityEvents takes precedence over global config.
     */
    protected static function resolvedTelegramActivityEvents(): array
    {
        // Allow per-model override via property
        if (property_exists(static::class, 'telegramActivityEvents')) {
            return (new static)->telegramActivityEvents; // @phpstan-ignore-line
        }

        return config('telegramlogs.activity_log.events', ['created', 'updated', 'deleted']);
    }

    /**
     * Dispatch the activity log for this model event.
     */
    protected function fireTelegramActivity(string $event): void
    {
        /** @var ActivityLogger $logger */
        $logger = app(ActivityLogger::class);
        $logger->logModelEvent($this, $event);
    }
}
