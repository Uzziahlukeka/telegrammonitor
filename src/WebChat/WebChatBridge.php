<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\WebChat;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Uzziahlukeka\TelegramMonitor\BotRegistry;

/**
 * Bridge between the web chat widget and the Telegram staff group.
 *
 * User side: HTTP API polled by the widget (no Telegram account needed).
 * Agent side: replies in the Telegram staff group via the existing webhook.
 */
final class WebChatBridge
{
    private string $botToken;

    private string $groupId;

    private Client $client;

    public function __construct()
    {
        $this->botToken = BotRegistry::token('support');
        $this->groupId = (string) config('telegramlogs.support_bot.group_id', '');
        $this->client = new Client(['timeout' => (int) config('telegramlogs.timeout', 10)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // User → Telegram group
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Forward a plain text message from the web user to the staff group.
     * Returns the Telegram group_message_id for agent-reply lookup.
     */
    public function forwardTextToGroup(WebChatSession $session, string $text): ?int
    {
        $header = $this->buildSessionHeader($session, isNew: ! $session->group_message_id);
        $header .= "\n💬 {$text}";

        $options = $session->group_message_id
            ? ['reply_to_message_id' => $session->group_message_id]
            : [];

        $result = $this->apiSendMessage($this->groupId, $header, $options);
        $msgId = $result['result']['message_id'] ?? null;

        if ($msgId && ! $session->group_message_id) {
            $session->update(['group_message_id' => $msgId]);
        }

        return $msgId;
    }

    /**
     * Upload a file from the web user and forward it to the staff group.
     * Stores the file locally, sends it to Telegram, then saves the path.
     *
     * @return array{group_message_id: ?int, header_message_id: ?int, media_path: ?string, media_name: string, media_mime: string, media_size: int}
     */
    public function forwardFileToGroup(WebChatSession $session, UploadedFile $file): array
    {
        $mediaName = $file->getClientOriginalName();
        $mediaMime = $file->getMimeType() ?? 'application/octet-stream';
        $mediaSize = $file->getSize();

        // Store locally so we can serve it to the user later
        $path = $file->store('webchat/uploads', 'local');

        // Send header to group
        $isNew = ! $session->group_message_id;
        $header = $this->buildSessionHeader($session, isNew: $isNew);
        $isImage = str_starts_with($mediaMime, 'image/');
        $header .= $isImage ? "\n🖼️ Photo : {$mediaName}" : "\n📎 Fichier : {$mediaName}";

        $headerOptions = $session->group_message_id
            ? ['reply_to_message_id' => $session->group_message_id]
            : [];

        $headerResult = $this->apiSendMessage($this->groupId, $header, $headerOptions);
        $headerMsgId = $headerResult['result']['message_id'] ?? null;

        if ($headerMsgId && $isNew) {
            $session->update(['group_message_id' => $headerMsgId]);
        }

        // Send the actual file as reply to the header
        $fileMsgId = null;

        if ($headerMsgId) {
            $fileOptions = ['reply_to_message_id' => $headerMsgId];
            $fileResult = $isImage
                ? $this->apiSendPhoto($this->groupId, $file->getRealPath(), $fileOptions)
                : $this->apiSendDocument($this->groupId, $file->getRealPath(), $mediaName, $fileOptions);

            $fileMsgId = $fileResult['result']['message_id'] ?? null;
        }

        // The media message is what agents reply to (more natural UX)
        $primaryMsgId = $fileMsgId ?? $headerMsgId;

        return [
            'group_message_id' => $primaryMsgId,
            'header_message_id' => $headerMsgId,
            'media_path' => $path,
            'media_name' => $mediaName,
            'media_mime' => $mediaMime,
            'media_size' => $mediaSize,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Agent → web user (called from the Telegram webhook)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle an agent reply in the staff group for a web chat message.
     * Saves the reply to DB so the widget can pick it up via polling.
     */
    public function handleAgentReply(array $telegramMessage, WebChatMessage $repliedTo): void
    {
        $session = $repliedTo->session;

        if ($session->isClosed()) {
            $this->apiSendMessage(
                (string) $telegramMessage['chat']['id'],
                '⚠️ Cette session web est déjà fermée.',
                ['reply_to_message_id' => $telegramMessage['message_id']]
            );

            return;
        }

        $from = $telegramMessage['from'] ?? [];
        $text = $telegramMessage['text'] ?? $telegramMessage['caption'] ?? '';

        // Agent commands
        if (str_starts_with($text, '/close') || str_starts_with($text, '/fermer')) {
            $this->closeSession($session, $from, $telegramMessage);

            return;
        }

        if (str_starts_with($text, '/status') || str_starts_with($text, '/statut')) {
            $this->showSessionStatus($session, $telegramMessage);

            return;
        }

        $agentName = $this->agentName($from);
        $mediaType = $this->detectMediaType($telegramMessage);

        WebChatMessage::create([
            'session_id' => $session->id,
            'direction' => 'agent_to_user',
            'content' => $text ?: null,
            'agent_name' => $agentName,
            'media_file_id' => $mediaType ? $this->getFileId($telegramMessage) : null,
            'media_name' => $this->getFileName($telegramMessage),
            'media_mime' => null,
            'group_message_id' => $telegramMessage['message_id'],
        ]);

        $session->update([
            'status' => 'in_progress',
            'last_activity_at' => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // File download proxy (agent-sent Telegram files → web user)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Download a Telegram file by its file_id and return the raw bytes + MIME type.
     * Used by the proxy endpoint so web users can download agent-sent files.
     *
     * @return array{content: string, mime: string, name: string}|null
     */
    public function downloadTelegramFile(string $fileId): ?array
    {
        try {
            $infoResp = $this->client->get(
                "https://api.telegram.org/bot{$this->botToken}/getFile?file_id={$fileId}"
            );
            $info = json_decode($infoResp->getBody()->getContents(), true);

            if (! ($info['ok'] ?? false)) {
                return null;
            }

            $filePath = $info['result']['file_path'];
            $downloadUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";

            $fileResp = $this->client->get($downloadUrl);
            $content = $fileResp->getBody()->getContents();

            // Guess MIME from path extension
            $ext = mb_strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                'mp4' => 'video/mp4',
                'mp3' => 'audio/mpeg',
                'ogg' => 'audio/ogg',
                default => 'application/octet-stream',
            };

            return [
                'content' => $content,
                'mime' => $mime,
                'name' => basename($filePath),
            ];
        } catch (Exception $e) {
            Log::error('WebChatBridge::downloadTelegramFile failed: '.$e->getMessage());

            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Session lifecycle
    // ─────────────────────────────────────────────────────────────────────────

    private function closeSession(WebChatSession $session, array $from, array $telegramMessage): void
    {
        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
            'last_activity_at' => now(),
        ]);

        // Save a system message so the widget shows "session closed"
        WebChatMessage::create([
            'session_id' => $session->id,
            'direction' => 'agent_to_user',
            'content' => config(
                'telegramlogs.web_chat.messages.session_closed',
                "✅ Votre demande a été résolue et la session est fermée.\n\nMerci de nous avoir contacté ! Si vous avez d'autres questions, ouvrez une nouvelle session."
            ),
            'agent_name' => $this->agentName($from),
            'group_message_id' => $telegramMessage['message_id'],
        ]);

        $this->apiSendMessage(
            (string) $telegramMessage['chat']['id'],
            "✅ Session fermée par {$this->agentName($from)}.",
            ['reply_to_message_id' => $telegramMessage['message_id']]
        );
    }

    private function showSessionStatus(WebChatSession $session, array $telegramMessage): void
    {
        $total = $session->messages()->count();
        $byUser = $session->messages()->where('direction', 'user_to_agent')->count();
        $byAgent = $session->messages()->where('direction', 'agent_to_user')->count();
        $lastActivity = $session->last_activity_at?->format('d/m/Y H:i') ?? 'N/A';

        $this->apiSendMessage(
            (string) $telegramMessage['chat']['id'],
            "🌐 Session web\n".
            "👤 {$session->display_label}\n".
            "📊 Statut : {$session->status}\n".
            "💬 Messages : {$total} ({$byUser} user / {$byAgent} agent)\n".
            "🕐 Ouverture : {$session->created_at->format('d/m/Y H:i')}\n".
            "🔄 Dernière activité : {$lastActivity}",
            ['reply_to_message_id' => $telegramMessage['message_id']]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSessionHeader(WebChatSession $session, bool $isNew): string
    {
        $badge = $isNew ? '🌐 Nouvelle session web' : '🌐 Session web';
        $label = $session->display_label;
        $time = now()->format('d/m/Y H:i');

        return "{$badge}\n👤 {$label}\n📅 {$time}";
    }

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

    // ─────────────────────────────────────────────────────────────────────────
    // Telegram API calls
    // ─────────────────────────────────────────────────────────────────────────

    private function apiSendMessage(string $chatId, string $text, array $options = []): array
    {
        try {
            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                ['json' => array_merge(['chat_id' => $chatId, 'text' => $text], $options)]
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Exception $e) {
            Log::error('WebChatBridge::sendMessage: '.$e->getMessage());

            return [];
        }
    }

    private function apiSendPhoto(string $chatId, string $filePath, array $options = []): array
    {
        try {
            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/sendPhoto",
                [
                    'multipart' => array_merge(
                        [
                            ['name' => 'chat_id', 'contents' => $chatId],
                            ['name' => 'photo', 'contents' => fopen($filePath, 'r'), 'filename' => basename($filePath)],
                        ],
                        $this->optionsToMultipart($options)
                    ),
                ]
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Exception $e) {
            Log::error('WebChatBridge::sendPhoto: '.$e->getMessage());

            return [];
        }
    }

    private function apiSendDocument(string $chatId, string $filePath, string $fileName, array $options = []): array
    {
        try {
            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/sendDocument",
                [
                    'multipart' => array_merge(
                        [
                            ['name' => 'chat_id', 'contents' => $chatId],
                            ['name' => 'document', 'contents' => fopen($filePath, 'r'), 'filename' => $fileName],
                        ],
                        $this->optionsToMultipart($options)
                    ),
                ]
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (Exception $e) {
            Log::error('WebChatBridge::sendDocument: '.$e->getMessage());

            return [];
        }
    }

    private function optionsToMultipart(array $options): array
    {
        return array_map(
            fn ($k, $v) => ['name' => $k, 'contents' => (string) $v],
            array_keys($options),
            array_values($options)
        );
    }
}
