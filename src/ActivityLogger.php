<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Activity logger inspired by spatie/laravel-activitylog.
 *
 * Sends model lifecycle events and custom activity descriptions
 * directly to your configured Telegram channel.
 *
 * Usage (fluent API):
 *
 *   activity()
 *       ->performedOn($post)
 *       ->causedBy(auth()->user())
 *       ->withProperties(['title' => $post->title])
 *       ->dispatch('Post was published');
 *
 *   activity('deleted a comment')->dispatch();
 */
final class ActivityLogger
{
    private ?Model $subject = null;

    private mixed $causer = null;

    private array $properties = [];

    private ?string $event = null;

    private TelegramMessage $telegram;

    public function __construct(TelegramMessage $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Set the model the activity is performed on.
     */
    public function performedOn(Model $model): ActivityLogger
    {
        $clone = clone $this;
        $clone->subject = $model;

        return $clone;
    }

    /**
     * Set who/what caused the activity.
     * Accepts a Model (e.g. User) or any scalar identifier.
     */
    public function causedBy(mixed $causer): ActivityLogger
    {
        $clone = clone $this;
        $clone->causer = $causer;

        return $clone;
    }

    /**
     * Attach extra properties to the activity notification.
     */
    public function withProperties(array $properties): ActivityLogger
    {
        $clone = clone $this;
        $clone->properties = array_merge($clone->properties, $properties);

        return $clone;
    }

    public function withProperty(string $key, mixed $value): ActivityLogger
    {
        return $this->withProperties([$key => $value]);
    }

    public function event(string $event): ActivityLogger
    {
        $clone = clone $this;
        $clone->event = $event;

        return $clone;
    }

    public function dispatch(string $description = ''): bool
    {
        if (! config('telegramlogs.activity_log.enabled', false)) {
            return false;
        }

        if (! $this->telegram->isActiveInCurrentEnvironment()) {
            return false;
        }

        $text = $this->buildMessage($description);

        $result = $this->telegram->send($text);
        $defaultChannel = config('logging.default', 'stack');
        $level = config('telegramlogs.activity_log.log_level', 'info');

        if ($defaultChannel !== 'telegram') {
            try {
                logger()->channel($defaultChannel)->log($level, '[TelegramActivity] '.$description, $this->properties);
            } catch (Throwable) {

            }
        }

        return $result !== false;
    }

    public function log(string $description): bool
    {
        return $this->dispatch($description);
    }

    /**
     * Internal: called by HasTelegramActivity trait on Eloquent events.
     */
    public function logModelEvent(Model $model, string $event): bool
    {
        if (! config('telegramlogs.activity_log.enabled', false)) {
            return false;
        }

        if (! in_array($event, config('telegramlogs.activity_log.events', ['created', 'updated', 'deleted']), true)) {
            return false;
        }

        $properties = [];

        if ($event === 'updated') {
            if (config('telegramlogs.activity_log.include_old_values', true)) {
                $properties['old'] = $model->getOriginal();
            }
            if (config('telegramlogs.activity_log.include_new_values', true)) {
                $properties['new'] = $model->getChanges();
            }
        } elseif (in_array($event, ['created', 'deleted', 'restored', 'forceDeleted'], true)) {
            $properties['attributes'] = $model->getAttributes();
        }

        $description = method_exists($model, 'getTelegramActivityDescription')
            ? $model->getTelegramActivityDescription($event)
            : ucfirst($event).' '.class_basename($model);

        $extraProps = method_exists($model, 'getTelegramActivityProperties')
            ? $model->getTelegramActivityProperties($event)
            : [];

        return $this
            ->performedOn($model)
            ->event($event)
            ->withProperties(array_merge($properties, $extraProps))
            ->dispatch($description);
    }
    private function buildMessage(string $description): string
    {
        $eventEmoji = $this->eventEmoji($this->event ?? '');
        $appName    = $this->escapeMarkdownV2(config('app.name', 'Laravel'));
        $env        = $this->escapeMarkdownV2(app()->environment());

        $lines = [
            "$eventEmoji *Activity* — $appName `[$env]`",
        ];

        if ($description !== '') {
            $lines[] = '';
            $lines[] = $this->escapeMarkdownV2($description);
        }

        if ($this->subject) {
            $subjectLabel = $this->escapeMarkdownV2(
                class_basename($this->subject).' #'.$this->subject->getKey()
            );
            $lines[] = '';
            $lines[] = "*Subject:* $subjectLabel";
        }

        if ($this->causer !== null) {
            $causerLabel = $this->causer instanceof Model
                ? class_basename($this->causer).' #'.$this->causer->getKey()
                : (string) $this->causer;

            $lines[] = '*By:* '.$this->escapeMarkdownV2($causerLabel);
        }

        if (! empty($this->properties)) {
            $json = json_encode(
                $this->properties,
                JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
            $lines[] = '';
            $lines[] = '*Properties:*';
            $lines[] = "```json\n$json\n```";
        }

        $lines[] = '';
        $lines[] = '🕐 `'.now()->format('Y-m-d H:i:s T').'`';

        return implode("\n", $lines);
    }

    private function escapeMarkdownV2(string $text): string
    {
        $parts = preg_split('/(```[\s\S]*?```|`[^`]*`)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $result .= preg_replace('/(?<!\\\\)([\[\]()\-~>#.+!\\\\=|{}])/', '\\\\$1', $part);
            } else {
                $result .= $part;
            }
        }

        return $result;
    }

    private function eventEmoji(string $event): string
    {
        return match ($event) {
            'created'      => '🟢',
            'updated'      => '🔵',
            'deleted'      => '🔴',
            'restored'     => '♻️',
            'forceDeleted' => '💣',
            default        => '📋',
        };
    }
}
