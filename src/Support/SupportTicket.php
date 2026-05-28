<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
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
        'last_activity_at',
        'closed_at',
    ];

    protected $casts = [
        'user_telegram_id' => 'integer',
        'group_message_id' => 'integer',
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

    public function getDisplayNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    public function getTicketTagAttribute(): string
    {
        return sprintf('#%04d', $this->ticket_number);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SupportTicket $ticket): void {
            if (! $ticket->ticket_number) {
                $ticket->ticket_number = (static::max('ticket_number') ?? 0) + 1;
            }
            $ticket->last_activity_at = now();
        });
    }
}
