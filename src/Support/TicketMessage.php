<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string $direction
 * @property string|null $agent_name
 * @property string|null $message_text
 * @property string|null $media_type
 * @property string|null $media_file_id
 * @property string|null $media_caption
 * @property string|null $media_file_name
 * @property int|null $telegram_message_id
 * @property int|null $group_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportTicket $ticket
 */
final class TicketMessage extends Model
{
    protected $table = 'ticket_messages';

    protected $fillable = [
        'ticket_id',
        'direction',
        'agent_name',
        'message_text',
        'media_type',
        'media_file_id',
        'media_caption',
        'media_file_name',
        'telegram_message_id',
        'group_message_id',
    ];

    protected $casts = [
        'telegram_message_id' => 'integer',
        'group_message_id' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function isFromUser(): bool
    {
        return $this->direction === 'user_to_agent';
    }

    public function isFromAgent(): bool
    {
        return $this->direction === 'agent_to_user';
    }

    public function hasMedia(): bool
    {
        return $this->media_type !== null;
    }
}
