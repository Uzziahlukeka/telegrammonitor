<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs\Support;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Support Bot — tunnel between users (private chat) and staff (group).
 *
 * Flow:
 *   User → private message → bot → group (ticket header + media)
 *   Agent → reply in group  → bot → user (forwarded via copyMessage)
 */
class SupportBotHandler
{
    private string $botToken;

    private string $supportGroupId;

    private Client $client;

    private int $timeout;

    public function __construct()
    {
        $this->botToken = (string) config('telegramlogs.support_bot.bot_token', config('telegramlogs.bot_token', ''));
        $this->supportGroupId = (string) config('telegramlogs.support_bot.group_id', '');
        $this->timeout = (int) config('telegramlogs.timeout', 10);
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

        // Agent must reply to a specific message
        if (! isset($message['reply_to_message'])) {
            return;
        }

        $repliedToId = (int) $message['reply_to_message']['message_id'];
        $from = $message['from'] ?? [];
        $text = $message['text'] ?? $message['caption'] ?? '';

        // Agent commands
        if (str_starts_with($text, '/close') || str_starts_with($text, '/fermer')) {
            $this->handleCloseCommand($repliedToId, $from, $message);

            return;
        }

        if (str_starts_with($text, '/status') || str_starts_with($text, '/statut')) {
            $this->handleStatusCommand($repliedToId, $message);

            return;
        }

        // Find the ticket from the replied-to group message
        $ticketMessage = TicketMessage::where('group_message_id', $repliedToId)->first();

        if (! $ticketMessage) {
            return; // Not a ticket-related message — ignore silently
        }

        $ticket = $ticketMessage->ticket;

        if ($ticket->isClosed()) {
            $this->sendMessage(
                (string) $message['chat']['id'],
                "⚠️ Ce ticket est déjà fermé. Impossible d'envoyer une réponse.",
                ['reply_to_message_id' => $message['message_id']]
            );

            return;
        }

        $agentName = $this->agentName($from);
        $userChatId = (string) $ticket->user_telegram_id;

        // Copy the agent's message to the user's private chat (no "forwarded from" header)
        $sentResult = $this->copyMessage($userChatId, (string) $message['chat']['id'], $message['message_id']);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'direction' => 'agent_to_user',
            'agent_name' => $agentName,
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

    // ─────────────────────────────────────────────────────────────────────────
    // Commands
    // ─────────────────────────────────────────────────────────────────────────

    private function handleUserCommand(string $command, array $from, string $chatId): void
    {
        $cmd = strtolower(explode('@', explode(' ', $command)[0])[0]);

        switch ($cmd) {
            case '/start':
                $name = $from['first_name'] ?? '';
                $greeting = $name ? "Bonjour {$name}! 👋\n\n" : "Bonjour! 👋\n\n";
                $this->sendMessage($chatId, $this->cfg('messages.welcome',
                    $greeting .
                    "Bienvenue dans notre support. Envoyez-nous votre message, photo ou document et un agent vous répondra.\n\n" .
                    "Commandes:\n" .
                    "• /status — état de votre ticket en cours\n" .
                    "• /help — aide"
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
                        "{$emoji} Ticket {$ticket->ticket_tag}\n" .
                        "Statut : {$ticket->status}\n" .
                        "Messages échangés : {$count}\n" .
                        "Ouvert le : {$ticket->created_at->format('d/m/Y à H:i')}"
                    );
                } else {
                    $this->sendMessage($chatId, "Vous n'avez pas de ticket ouvert. Envoyez-nous un message pour en créer un.");
                }
                break;

            default:
                $this->sendMessage($chatId, $this->cfg('messages.help',
                    "ℹ️ Aide\n\n" .
                    "Envoyez simplement votre message et un agent vous répondra.\n\n" .
                    "Vous pouvez envoyer :\n" .
                    "• Texte\n• Photos\n• Documents\n• Vidéos\n• Messages vocaux\n\n" .
                    "Commandes :\n• /status — état de votre ticket\n• /help — cette aide"
                ));
                break;
        }
    }

    private function handleCloseCommand(int $repliedToId, array $from, array $message): void
    {
        $ticketMessage = TicketMessage::where('group_message_id', $repliedToId)->first();
        $groupChatId = (string) $message['chat']['id'];

        if (! $ticketMessage) {
            $this->sendMessage($groupChatId, '❌ Ticket introuvable pour ce message.', [
                'reply_to_message_id' => $message['message_id'],
            ]);

            return;
        }

        $ticket = $ticketMessage->ticket;

        if ($ticket->isClosed()) {
            $this->sendMessage($groupChatId, "ℹ️ Ce ticket est déjà fermé.", [
                'reply_to_message_id' => $message['message_id'],
            ]);

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

        // Confirm in group
        $this->sendMessage($groupChatId, "✅ Ticket {$ticket->ticket_tag} fermé par {$this->agentName($from)}.", [
            'reply_to_message_id' => $message['message_id'],
        ]);
    }

    private function handleStatusCommand(int $repliedToId, array $message): void
    {
        $ticketMessage = TicketMessage::where('group_message_id', $repliedToId)->first();
        $groupChatId = (string) $message['chat']['id'];

        if (! $ticketMessage) {
            $this->sendMessage($groupChatId, '❌ Ticket introuvable pour ce message.', [
                'reply_to_message_id' => $message['message_id'],
            ]);

            return;
        }

        $ticket = $ticketMessage->ticket;
        $total = $ticket->messages()->count();
        $byUser = $ticket->messages()->where('direction', 'user_to_agent')->count();
        $byAgent = $ticket->messages()->where('direction', 'agent_to_user')->count();
        $userTag = $ticket->username ? " (@{$ticket->username})" : '';

        $this->sendMessage($groupChatId,
            "📋 Ticket {$ticket->ticket_tag}\n" .
            "👤 {$ticket->display_name}{$userTag}\n" .
            "🆔 ID Telegram : {$ticket->user_telegram_id}\n" .
            "📊 Statut : {$ticket->status}\n" .
            "💬 Messages : {$total} ({$byUser} utilisateur / {$byAgent} agent)\n" .
            "🕐 Ouvert : {$ticket->created_at->format('d/m/Y H:i')}\n" .
            "🔄 Dernière activité : {$ticket->last_activity_at?->format('d/m/Y H:i') ?? 'N/A'}",
            ['reply_to_message_id' => $message['message_id']]
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

        // All subsequent user messages reply to the ticket's anchor message in group
        $replyOptions = $ticket->group_message_id
            ? ['reply_to_message_id' => $ticket->group_message_id]
            : [];

        $headerResult = $this->sendMessage($this->supportGroupId, $header, $replyOptions);

        if ($headerMsgId = $headerResult['result']['message_id'] ?? null) {
            $ids[] = $headerMsgId;

            // Set the anchor group message on first interaction
            if (! $ticket->group_message_id) {
                $ticket->update(['group_message_id' => $headerMsgId]);
            }
        }

        // If there is media, copy it to the group as a reply to the header
        if ($mediaType !== null && isset($headerMsgId)) {
            $mediaResult = $this->copyMessage(
                $this->supportGroupId,
                (string) $message['chat']['id'],
                $message['message_id'],
                ['reply_to_message_id' => $headerMsgId]
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

        return SupportTicket::create([
            'user_telegram_id' => $from['id'],
            'username' => $from['username'] ?? null,
            'first_name' => $from['first_name'] ?? 'Utilisateur',
            'last_name' => $from['last_name'] ?? null,
            'status' => 'open',
        ]);
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
        return trim(($from['first_name'] ?? 'Agent').' '.($from['last_name'] ?? ''));
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

    // ─────────────────────────────────────────────────────────────────────────
    // Config helper
    // ─────────────────────────────────────────────────────────────────────────

    private function cfg(string $key, string $default): string
    {
        return (string) config("telegramlogs.support_bot.{$key}", $default);
    }
}
