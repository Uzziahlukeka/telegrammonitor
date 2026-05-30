<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_number
 * @property int $user_telegram_id
 * @property string|null $username
 * @property string $first_name
 * @property string|null $last_name
 * @property string $status
 * @property int|null $group_message_id
 * @property int|null $topic_id
 * @property int|null $assigned_agent_id
 * @property string|null $assigned_agent_name
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $display_name
 * @property-read string $ticket_tag
 * @property-read Collection<int, TicketMessage> $messages
 */
final class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $fillable = [
        'ticket_number',
        'user_telegram_id',
        'username',
        'first_name',
        'last_name',
        'status',
        'group_message_id',
        'topic_id',
        'assigned_agent_id',
        'assigned_agent_name',
        'last_activity_at',
        'closed_at',
    ];

    protected $casts = [
        'user_telegram_id' => 'integer',
        'group_message_id' => 'integer',
        'topic_id' => 'integer',
        'assigned_agent_id' => 'integer',
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    public function isOpen(): bool
    {
        return $this->status !== 'closed';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isAssigned(): bool
    {
        return $this->assigned_agent_id !== null;
    }

    public function getDisplayNameAttribute(): string
    {
        return mb_trim($this->first_name.' '.($this->last_name ?? ''));
    }

    public function getTicketTagAttribute(): string
    {
        return sprintf('#%04d', $this->ticket_number);
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (SupportTicket $ticket): void {
            if (! $ticket->ticket_number) {
                $ticket->ticket_number = (static::max('ticket_number') ?? 0) + 1;
            }
            $ticket->last_activity_at = now();
        });
    }
}
