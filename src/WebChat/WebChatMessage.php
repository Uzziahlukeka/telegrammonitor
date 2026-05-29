<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\WebChat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebChatMessage extends Model
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
