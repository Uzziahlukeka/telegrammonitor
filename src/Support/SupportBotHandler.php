<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\Support;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Uzhlaravel\Telegramlogs\BotRegistry;
use Uzhlaravel\Telegramlogs\WebChat\WebChatBridge;
use Uzhlaravel\Telegramlogs\WebChat\WebChatMessage;

final class SupportBotHandler
{
    private string $botToken;

    private string $supportGroupId;

    private Client $client;

    private int $timeout;

    private bool $useTopics;

    public function __construct()
    {
        $this->botToken = BotRegistry::token('support');
        $this->supportGroupId = (string) config('telegramlogs.support_bot.group_id', '');
        $this->timeout = (int) config('telegramlogs.timeout', 10);
        $this->useTopics = (bool) config('telegramlogs.support_bot.use_topics', false);
        $this->client = new Client(['timeout' => $this->timeout]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function processUpdate(array $update): void
    {
        if (isset($update['message'])) {
            $this->processMessage($update['message']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Webhook management (used by the Artisan command)
    // ─────────────────────────────────────────────────────────────────────────

    public function setWebhook(string $url, ?string $secret = null): array
    {
        try {
            $payload = ['url' => $url];
            if ($secret) {
                $payload['secret_token'] = $secret;
            }

            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/setWebhook",
                ['json' => $payload]
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Exception $e) {
            Log::error('SupportBotHandler::setWebhook failed: '.$e->getMessage());

            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    public function deleteWebhook(): array
    {
        try {
            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/deleteWebhook"
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Exception $e) {
            Log::error('SupportBotHandler::deleteWebhook failed: '.$e->getMessage());

            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    public function getBotInfo(): array
    {
        try {
            $response = $this->client->get(
                "https://api.telegram.org/bot{$this->botToken}/getMe"
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Exception $e) {
            Log::error('SupportBotHandler::getBotInfo failed: '.$e->getMessage());

            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    private function processMessage(array $message): void
    {
        $chatType = $message['chat']['type'] ?? 'private';

        if ($chatType === 'private') {
            $this->handleUserMessage($message);
        } elseif (in_array($chatType, ['group', 'supergroup'], true)) {
            $this->handleGroupMessage($message);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // User → Bot (private chat)
    // ─────────────────────────────────────────────────────────────────────────

    private function handleUserMessage(array $message): void
    {
        $from = $message['from'];
        $chatId = (string) $message['chat']['id'];
        $text = $message['text'] ?? '';

        if ($text !== '' && str_starts_with($text, '/')) {
            $this->handleUserCommand($text, $from, $chatId);

            return;
        }

        $ticket = $this->findOrCreateTicket($from);
        $isNew = $ticket->wasRecentlyCreated;

        $this->syncUserInfo($ticket, $from);

        // Forward everything to the staff group
        $groupMessageIds = $this->relayUserMessageToGroup($message, $ticket, $isNew);

        // Persist each group message as a TicketMessage for agent-reply lookup
        $mediaType = $this->detectMediaType($message);

        foreach ($groupMessageIds as $index => $groupMsgId) {
            // The last entry is the "content" message (media or text); the first may be the header
            $isHeaderOnly = $mediaType !== null && $index === 0 && count($groupMessageIds) > 1;

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'direction' => 'user_to_agent',
                'message_text' => $isHeaderOnly ? null : ($text ?: null),
                'media_type' => $isHeaderOnly ? null : $mediaType,
                'media_file_id' => $isHeaderOnly ? null : $this->getFileId($message),
                'media_caption' => $isHeaderOnly ? null : ($message['caption'] ?? null),
                'media_file_name' => $isHeaderOnly ? null : $this->getFileName($message),
                'telegram_message_id' => $message['message_id'],
                'group_message_id' => $groupMsgId,
            ]);
        }

        $ticket->update(['last_activity_at' => now()]);

        if ($isNew) {
            $this->sendMessage(
                $chatId,
                $this->cfg('messages.ticket_created',
                    "✅ Votre demande a été enregistrée ({$ticket->ticket_tag}).\n\nUn agent va vous répondre dès que possible. Merci de votre patience."
                )
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Agent → Bot (group reply)
    // ─────────────────────────────────────────────────────────────────────────

    private function handleGroupMessage(array $message): void
    {
        // Only listen to the configured support group
        if ((string) $message['chat']['id'] !== $this->supportGroupId) {
            return;
        }

        // Ignore forum service messages (topic created/closed/reopened/edited, etc.)
        if ($this->isForumServiceMessage($message)) {
            return;
        }

        $from = $message['from'] ?? [];
        $text = $message['text'] ?? $message['caption'] ?? '';

        // ── Topic mode: a message typed directly inside a ticket's forum topic ──
        // In a forum group, agents simply write in the topic — no reply required.
        if ($this->useTopics && isset($message['message_thread_id'])) {
            $ticket = SupportTicket::where('topic_id', (int) $message['message_thread_id'])->first();

            if ($ticket) {
                $this->handleAgentTicketMessage($ticket, $message, $from, $text);

                return;
            }
        }

        // ── Reply-based flow (flat-mode tickets and the web chat widget) ────────
        if (! isset($message['reply_to_message'])) {
            return;
        }

        $repliedToId = (int) $message['reply_to_message']['message_id'];

        // Web chat sessions are always reply-based, even when ticket topics are on.
        $webChatMsg = WebChatMessage::where('group_message_id', $repliedToId)->first();

        if ($webChatMsg) {
            app(WebChatBridge::class)->handleAgentReply($message, $webChatMsg);

            return;
        }

        $ticketMessage = TicketMessage::where('group_message_id', $repliedToId)->first();

        if (! $ticketMessage) {
            return; // Not a ticket-related message — ignore silently
        }

        $this->handleAgentTicketMessage($ticketMessage->ticket, $message, $from, $text);
    }

    /**
     * Relay an agent message (topic or reply based) to the ticket owner's private chat.
     * The first agent to respond is automatically assigned as the ticket's correspondent.
     *
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $from
     */
    private function handleAgentTicketMessage(SupportTicket $ticket, array $message, array $from, string $text): void
    {
        // Agent commands operate on the ticket without needing a reply.
        if (str_starts_with($text, '/close') || str_starts_with($text, '/fermer')) {
            $this->closeTicket($ticket, $from, $message);

            return;
        }

        if (str_starts_with($text, '/status') || str_starts_with($text, '/statut')) {
            $this->sendTicketStatus($ticket, $message);

            return;
        }

        if ($ticket->isClosed()) {
            $this->sendMessage(
                $this->supportGroupId,
                "⚠️ Ce ticket est déjà fermé. Impossible d'envoyer une réponse.",
                array_merge(['reply_to_message_id' => $message['message_id']], $this->threadOptions($ticket))
            );

            return;
        }

        // First agent to reply becomes the ticket's single correspondent.
        $this->assignAgentIfNeeded($ticket, $from);

        $userChatId = (string) $ticket->user_telegram_id;

        // Copy the agent's message to the user's private chat (no "forwarded from" header)
        $sentResult = $this->copyMessage($userChatId, (string) $message['chat']['id'], $message['message_id']);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'direction' => 'agent_to_user',
            'agent_name' => $this->agentName($from),
            'message_text' => $text ?: null,
            'media_type' => $this->detectMediaType($message),
            'media_file_id' => $this->getFileId($message),
            'media_caption' => $message['caption'] ?? null,
            'media_file_name' => $this->getFileName($message),
            'telegram_message_id' => $sentResult['result']['message_id'] ?? null,
            'group_message_id' => $message['message_id'],
        ]);

        $ticket->update([
            'status' => 'in_progress',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Assign the first responding agent as the ticket's correspondent and announce it.
     *
     * @param  array<string, mixed>  $from
     */
    private function assignAgentIfNeeded(SupportTicket $ticket, array $from): void
    {
        if ($ticket->isAssigned() || ! isset($from['id'])) {
            return;
        }

        $agentName = $this->agentName($from);

        $ticket->update([
            'assigned_agent_id' => (int) $from['id'],
            'assigned_agent_name' => $agentName,
        ]);

        $this->sendMessage(
            $this->supportGroupId,
            "👤 {$agentName} a pris en charge le ticket {$ticket->ticket_tag}.",
            $this->threadOptions($ticket)
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Commands
    // ─────────────────────────────────────────────────────────────────────────

    private function handleUserCommand(string $command, array $from, string $chatId): void
    {
        $cmd = mb_strtolower(explode('@', explode(' ', $command)[0])[0]);

        switch ($cmd) {
            case '/start':
                $name = $from['first_name'] ?? '';
                $greeting = $name ? "Bonjour {$name}! 👋\n\n" : "Bonjour! 👋\n\n";
                $this->sendMessage($chatId, $this->cfg('messages.welcome',
                    $greeting.
                    "Bienvenue dans notre support. Envoyez-nous votre message, photo ou document et un agent vous répondra.\n\n".
                    "Commandes:\n".
                    "• /status — état de votre ticket en cours\n".
                    '• /help — aide'
                ));
                break;

            case '/status':
                $ticket = SupportTicket::where('user_telegram_id', $from['id'])
                    ->whereIn('status', ['open', 'in_progress'])
                    ->latest()
                    ->first();

                if ($ticket) {
                    $emoji = match ($ticket->status) {
                        'open' => '🔵',
                        'in_progress' => '🟡',
                        default => '⚪',
                    };
                    $count = $ticket->messages()->count();
                    $this->sendMessage($chatId,
                        "{$emoji} Ticket {$ticket->ticket_tag}\n".
                        "Statut : {$ticket->status}\n".
                        "Messages échangés : {$count}\n".
                        "Ouvert le : {$ticket->created_at->format('d/m/Y à H:i')}"
                    );
                } else {
                    $this->sendMessage($chatId, "Vous n'avez pas de ticket ouvert. Envoyez-nous un message pour en créer un.");
                }
                break;

            default:
                $this->sendMessage($chatId, $this->cfg('messages.help',
                    "ℹ️ Aide\n\n".
                    "Envoyez simplement votre message et un agent vous répondra.\n\n".
                    "Vous pouvez envoyer :\n".
                    "• Texte\n• Photos\n• Documents\n• Vidéos\n• Messages vocaux\n\n".
                    "Commandes :\n• /status — état de votre ticket\n• /help — cette aide"
                ));
                break;
        }
    }

    /**
     * Close and resolve a ticket, notify the user and (in topic mode) close the forum topic.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $message
     */
    private function closeTicket(SupportTicket $ticket, array $from, array $message): void
    {
        $replyOptions = array_merge(
            ['reply_to_message_id' => $message['message_id']],
            $this->threadOptions($ticket)
        );

        if ($ticket->isClosed()) {
            $this->sendMessage($this->supportGroupId, 'ℹ️ Ce ticket est déjà fermé.', $replyOptions);

            return;
        }

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
            'last_activity_at' => now(),
        ]);

        // Notify user
        $this->sendMessage((string) $ticket->user_telegram_id, $this->cfg('messages.ticket_closed',
            "✅ Votre ticket {$ticket->ticket_tag} a été résolu et fermé.\n\nMerci de nous avoir contacté ! Si vous avez d'autres questions, n'hésitez pas à nous écrire."
        ));

        // Confirm in group / topic
        $this->sendMessage($this->supportGroupId, "✅ Ticket {$ticket->ticket_tag} fermé par {$this->agentName($from)}.", $replyOptions);

        // Archive the forum topic so the staff view stays tidy.
        if ($this->useTopics && $ticket->topic_id) {
            $this->closeForumTopic($ticket->topic_id);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function sendTicketStatus(SupportTicket $ticket, array $message): void
    {
        $total = $ticket->messages()->count();
        $byUser = $ticket->messages()->where('direction', 'user_to_agent')->count();
        $byAgent = $ticket->messages()->where('direction', 'agent_to_user')->count();
        $userTag = $ticket->username ? " (@{$ticket->username})" : '';
        $lastActivity = $ticket->last_activity_at?->format('d/m/Y H:i') ?? 'N/A';
        $assignedTo = $ticket->assigned_agent_name ?? 'non assigné';

        $this->sendMessage($this->supportGroupId,
            "📋 Ticket {$ticket->ticket_tag}\n".
            "👤 {$ticket->display_name}{$userTag}\n".
            "🆔 ID Telegram : {$ticket->user_telegram_id}\n".
            "🙋 Correspondant : {$assignedTo}\n".
            "📊 Statut : {$ticket->status}\n".
            "💬 Messages : {$total} ({$byUser} utilisateur / {$byAgent} agent)\n".
            "🕐 Ouvert : {$ticket->created_at->format('d/m/Y H:i')}\n".
            "🔄 Dernière activité : {$lastActivity}",
            array_merge(['reply_to_message_id' => $message['message_id']], $this->threadOptions($ticket))
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relay helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send a user message to the staff group.
     * Returns an array of group message IDs created (header + optional media copy).
     *
     * @return int[]
     */
    private function relayUserMessageToGroup(array $message, SupportTicket $ticket, bool $isNew): array
    {
        $mediaType = $this->detectMediaType($message);
        $text = $message['text'] ?? '';
        $ids = [];

        // Build the header for this message
        $userTag = $ticket->username ? "@{$ticket->username}" : "ID:{$ticket->user_telegram_id}";
        $statusBadge = $isNew ? '🆕 Nouveau ticket' : "📨 Ticket {$ticket->ticket_tag}";

        $header = "{$statusBadge}\n";
        $header .= "👤 {$ticket->display_name} ({$userTag})\n";
        $header .= '📅 '.now()->format('d/m/Y H:i')."\n";

        if ($mediaType) {
            $mediaLabel = match ($mediaType) {
                'photo' => '🖼️ Photo',
                'document' => '📎 Document'.($this->getFileName($message) ? ' : '.$this->getFileName($message) : ''),
                'video' => '🎥 Vidéo',
                'audio' => '🎵 Audio'.($this->getFileName($message) ? ' : '.$this->getFileName($message) : ''),
                'voice' => '🎤 Message vocal',
                'sticker' => '🎭 Sticker',
                'video_note' => '⭕ Vidéo circulaire',
                default => "📎 {$mediaType}",
            };
            $header .= $mediaLabel;
            if (isset($message['caption'])) {
                $header .= "\n💬 {$message['caption']}";
            }
        } else {
            $header .= "💬 {$text}";
        }

        // Topic mode: deliver into the ticket's dedicated forum topic.
        // Flat mode: thread subsequent messages under the ticket's anchor message.
        if ($this->useTopics && $ticket->topic_id) {
            $headerOptions = $this->threadOptions($ticket);
        } else {
            $headerOptions = $ticket->group_message_id
                ? ['reply_to_message_id' => $ticket->group_message_id]
                : [];
        }

        $headerResult = $this->sendMessage($this->supportGroupId, $header, $headerOptions);

        if ($headerMsgId = $headerResult['result']['message_id'] ?? null) {
            $ids[] = $headerMsgId;

            // Set the anchor group message on first interaction (flat-mode threading)
            if (! $ticket->group_message_id) {
                $ticket->update(['group_message_id' => $headerMsgId]);
            }
        }

        // If there is media, copy it to the group right under the header.
        if ($mediaType !== null && isset($headerMsgId)) {
            $mediaOptions = $this->useTopics && $ticket->topic_id
                ? $this->threadOptions($ticket)
                : ['reply_to_message_id' => $headerMsgId];

            $mediaResult = $this->copyMessage(
                $this->supportGroupId,
                (string) $message['chat']['id'],
                $message['message_id'],
                $mediaOptions
            );

            if ($mediaMsgId = $mediaResult['result']['message_id'] ?? null) {
                $ids[] = $mediaMsgId;
            }
        }

        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ticket management
    // ─────────────────────────────────────────────────────────────────────────

    private function findOrCreateTicket(array $from): SupportTicket
    {
        $existing = SupportTicket::where('user_telegram_id', $from['id'])
            ->whereIn('status', ['open', 'in_progress'])
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $ticket = SupportTicket::create([
            'user_telegram_id' => $from['id'],
            'username' => $from['username'] ?? null,
            'first_name' => $from['first_name'] ?? 'Utilisateur',
            'last_name' => $from['last_name'] ?? null,
            'status' => 'open',
        ]);

        // In topic mode, give each ticket its own forum topic so it reads like a
        // private one-to-one conversation on the staff side.
        if ($this->useTopics) {
            $userTag = $ticket->username ? "@{$ticket->username}" : "ID:{$ticket->user_telegram_id}";
            $topicId = $this->createForumTopic("{$ticket->ticket_tag} · {$ticket->display_name} ({$userTag})");

            if ($topicId !== null) {
                $ticket->update(['topic_id' => $topicId]);
            }
        }

        return $ticket;
    }

    private function syncUserInfo(SupportTicket $ticket, array $from): void
    {
        $ticket->update([
            'username' => $from['username'] ?? $ticket->username,
            'first_name' => $from['first_name'] ?? $ticket->first_name,
            'last_name' => $from['last_name'] ?? $ticket->last_name,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Media helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function detectMediaType(array $message): ?string
    {
        foreach (['photo', 'document', 'video', 'audio', 'voice', 'sticker', 'video_note'] as $type) {
            if (isset($message[$type])) {
                return $type;
            }
        }

        return null;
    }

    private function getFileId(array $message): ?string
    {
        if (isset($message['photo'])) {
            // Telegram sends multiple sizes; the last one is the highest resolution
            $photos = $message['photo'];

            return end($photos)['file_id'] ?? null;
        }

        foreach (['document', 'video', 'audio', 'voice', 'sticker', 'video_note'] as $type) {
            if (isset($message[$type]['file_id'])) {
                return $message[$type]['file_id'];
            }
        }

        return null;
    }

    private function getFileName(array $message): ?string
    {
        return $message['document']['file_name'] ?? $message['audio']['file_name'] ?? null;
    }

    private function agentName(array $from): string
    {
        return mb_trim(($from['first_name'] ?? 'Agent').' '.($from['last_name'] ?? ''));
    }

    /**
     * Telegram send options targeting the ticket's forum topic, when topic mode is on.
     *
     * @return array<string, int>
     */
    private function threadOptions(SupportTicket $ticket): array
    {
        return $this->useTopics && $ticket->topic_id
            ? ['message_thread_id' => $ticket->topic_id]
            : [];
    }

    /**
     * Whether a group update is a forum service event we should ignore
     * (topic created/closed/reopened/edited, hidden/unhidden, etc.).
     *
     * @param  array<string, mixed>  $message
     */
    private function isForumServiceMessage(array $message): bool
    {
        foreach ([
            'forum_topic_created',
            'forum_topic_edited',
            'forum_topic_closed',
            'forum_topic_reopened',
            'general_forum_topic_hidden',
            'general_forum_topic_unhidden',
        ] as $key) {
            if (isset($message[$key])) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Telegram API calls
    // ─────────────────────────────────────────────────────────────────────────

    private function sendMessage(string $chatId, string $text, array $options = []): array
    {
        try {
            $payload = array_merge(['chat_id' => $chatId, 'text' => $text], $options);

            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                ['json' => $payload]
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (RequestException $e) {
            Log::error('SupportBotHandler::sendMessage failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
            Log::error('SupportBotHandler::sendMessage unexpected error: '.$e->getMessage());
        }

        return [];
    }

    /**
     * Create a forum topic in the staff group and return its message_thread_id.
     * Returns null if topics are unavailable (e.g. the group is not a forum).
     */
    private function createForumTopic(string $name): ?int
    {
        try {
            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/createForumTopic",
                ['json' => [
                    'chat_id' => $this->supportGroupId,
                    'name' => mb_substr($name, 0, 128),
                ]]
            );

            $data = json_decode($response->getBody()->getContents(), true) ?? [];

            if (! ($data['ok'] ?? false)) {
                Log::warning('SupportBotHandler::createForumTopic rejected by Telegram', [
                    'description' => $data['description'] ?? null,
                ]);

                return null;
            }

            return isset($data['result']['message_thread_id'])
                ? (int) $data['result']['message_thread_id']
                : null;
        } catch (Exception $e) {
            Log::error('SupportBotHandler::createForumTopic failed: '.$e->getMessage());

            return null;
        }
    }

    private function closeForumTopic(int $threadId): void
    {
        try {
            $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/closeForumTopic",
                ['json' => [
                    'chat_id' => $this->supportGroupId,
                    'message_thread_id' => $threadId,
                ]]
            );
        } catch (Exception $e) {
            Log::error('SupportBotHandler::closeForumTopic failed: '.$e->getMessage());
        }
    }

    private function copyMessage(string $chatId, string $fromChatId, int $messageId, array $options = []): array
    {
        try {
            $payload = array_merge([
                'chat_id' => $chatId,
                'from_chat_id' => $fromChatId,
                'message_id' => $messageId,
            ], $options);

            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/copyMessage",
                ['json' => $payload]
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (RequestException $e) {
            Log::error('SupportBotHandler::copyMessage failed', [
                'from_chat_id' => $fromChatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
            Log::error('SupportBotHandler::copyMessage unexpected error: '.$e->getMessage());
        }

        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Config helper
    // ─────────────────────────────────────────────────────────────────────────

    private function cfg(string $key, string $default): string
    {
        return (string) config("telegramlogs.support_bot.{$key}", $default);
    }
}
