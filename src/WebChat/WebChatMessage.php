<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\WebChat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $session_id
 * @property string $direction
 * @property string|null $content
 * @property string|null $agent_name
 * @property string|null $media_path
 * @property string|null $media_name
 * @property string|null $media_mime
 * @property int|null $media_size
 * @property string|null $media_file_id
 * @property int|null $group_message_id
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebChatSession $session
 */
final class WebChatMessage extends Model
{
    protected $table = 'web_chat_messages';

    protected $fillable = [
        'session_id',
        'direction',
        'content',
        'agent_name',
        'media_path',
        'media_name',
        'media_mime',
        'media_size',
        'media_file_id',
        'group_message_id',
        'read_at',
    ];

    protected $casts = [
        'group_message_id' => 'integer',
        'media_size' => 'integer',
        'read_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WebChatSession::class, 'session_id');
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
        return $this->media_path !== null || $this->media_file_id !== null;
    }
}
