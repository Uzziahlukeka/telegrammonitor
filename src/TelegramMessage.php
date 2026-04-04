<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

final class TelegramMessage
{
    private string $botToken;

    private string $chatId;

    private ?string $topicId;

    private Client $client;

    private int $timeout;

    private ?string $parseMode;

    public function __construct()
    {
        $this->botToken = config('telegramlogs.bot_token', '');
        $this->chatId = config('telegramlogs.chat_id', '');
        $this->topicId = config('telegramlogs.topic_message_id');
        $this->parseMode = config('telegramlogs.formatting.parse_mode');
        $this->timeout = (int) config('telegramlogs.timeout', 10);

        $this->client = new Client([
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Determine whether the current environment allows sending messages.
     */
    public function isActiveInCurrentEnvironment(): bool
    {
        $environments = config('telegramlogs.environments', 'production');

        if ($environments === '*' || $environments === null) {
            return true;
        }

        $allowed = array_map('trim', explode(',', (string) $environments));

        return in_array(app()->environment(), $allowed, true);
    }

    /**
     * Send a simple text message.
     */
    public function message(string $text): array|bool
    {
        if (! $this->isActiveInCurrentEnvironment()) {
            return false;
        }

        if (empty($this->botToken) || empty($this->chatId)) {
            Log::warning('TelegramMessage: bot token or chat ID is not configured.');

            return false;
        }

        return $this->sendMessage($text);
    }

    /**
     * Send a message with custom Telegram API parameters.
     */
    public function send(string $text, array $options = []): array|bool
    {
        if (! $this->isActiveInCurrentEnvironment()) {
            return false;
        }

        return $this->sendMessage($text, $options);
    }

    /**
     * Send a message to a specific chat (overrides configured chat_id).
     */
    public function toChat(string $chatId, string $text, array $options = []): array|bool
    {
        if (! $this->isActiveInCurrentEnvironment()) {
            return false;
        }

        $originalChatId = $this->chatId;
        $this->chatId = $chatId;

        $result = $this->sendMessage($text, $options);

        $this->chatId = $originalChatId;

        return $result;
    }

    /**
     * Test the connection by sending a test message.
     */
    public function test(): array|bool
    {
        $appName = config('app.name', 'Laravel');
        $env = app()->environment();

        return $this->sendMessage("✅ Telegram connection test successful!\n*{$appName}* `[{$env}]`");
    }

    /**
     * Retrieve bot information from the Telegram API.
     */
    public function getBotInfo(): array|bool
    {
        try {
            $response = $this->client->get(
                'https://api.telegram.org/bot'.$this->botToken.'/getMe'
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            Log::error('TelegramMessage: failed to get bot info: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Internal send implementation.
     */
    private function sendMessage(string $text, array $options = []): array|bool
    {
        try {
            $appName = config('app.name', 'Laravel');
            $header = "App: .$appName. \n\n";
            $payload = array_merge([
                'chat_id' => $this->chatId,
                'text' => $header.$text,
            ], $options);

            if ($this->topicId) {
                $payload['message_thread_id'] = $this->topicId;
            }

            if ($this->parseMode && ! isset($payload['parse_mode'])) {
                $payload['parse_mode'] = $this->parseMode;
            }

            $response = $this->client->post(
                'https://api.telegram.org/bot'.$this->botToken.'/sendMessage',
                ['json' => $payload]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('TelegramMessage: API request failed: '.$e->getMessage());

            return false;
        } catch (Exception $e) {
            Log::error('TelegramMessage: unexpected error: '.$e->getMessage());

            return false;
        }
    }
}
