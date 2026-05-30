<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\WebChat;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $session_token
 * @property string|null $display_name
 * @property string|null $email
 * @property string $status
 * @property int|null $group_message_id
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $display_label
 * @property-read Collection<int, WebChatMessage> $messages
 */
final class WebChatSession extends Model
{
    protected $table = 'web_chat_sessions';

    protected $fillable = [
        'session_token',
        'display_name',
        'email',
        'status',
        'group_message_id',
        'last_activity_at',
        'closed_at',
    ];

    protected $casts = [
        'group_message_id' => 'integer',
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WebChatMessage::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status !== 'closed';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function getDisplayLabelAttribute(): string
    {
        if ($this->display_name) {
            return $this->email
                ? "{$this->display_name} ({$this->email})"
                : $this->display_name;
        }

        return $this->email ?? 'Visiteur #'.$this->id;
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (WebChatSession $session): void {
            if (! $session->session_token) {
                $session->session_token = (string) Str::uuid();
            }
            $session->last_activity_at = now();
        });
    }
}
