<?php

namespace Uzhlaravel\Telegramlogs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class TelegramMessage
{
    protected string $botToken;

    protected string $chatId;

    protected ?string $topicId;

    protected Client $client;

    protected int $timeout;

    protected bool $enabled;

    protected ?string $parseMode;

    public function __construct()
    {
        $this->botToken = config('telegramlogs.bot_token', '');
        $this->chatId = config('telegramlogs.chat_id', '');
        $this->topicId = config('telegramlogs.topic_message_id');
        $this->parseMode = config('telegramlogs.parse_mode');

        $this->client = new Client();
    }

    /**
     * Send a simple text message
     */
    public function message(string $text): array|bool
    {

        if (empty($this->botToken) || empty($this->chatId)) {
            Log::warning('Telegram bot token or chat ID is not configured');

            return false;
        }

        return $this->sendMessage($text);
    }

    /**
     * Send message with custom parameters
     */
    public function send(string $text, array $options = []): array|bool
    {
        return $this->sendMessage($text, $options);
    }

    /**
     * Send message to a different chat
     */
    public function toChat(string $chatId, string $text, array $options = []): array|bool
    {

        $originalChatId = $this->chatId;
        $this->chatId = $chatId;

        $result = $this->sendMessage($text, $options);

        $this->chatId = $originalChatId;

        return $result;
    }

    /**
     * Internal method to send message
     */
    protected function sendMessage(string $text, array $options = []): array|bool
    {
        try {
            $payload = array_merge([
                'chat_id' => $this->chatId,
                'text' => $text,
            ], $options);

            // Add topic ID if set
            if ($this->topicId) {
                $payload['message_thread_id'] = $this->topicId;
            }

            // Add default parse mode if set and not overridden
            if ($this->parseMode && ! isset($payload['parse_mode'])) {
                $payload['parse_mode'] = $this->parseMode;
            }

            $response = $this->client->post(
                'https://api.telegram.org/bot'.$this->botToken.'/sendMessage',
                ['json' => $payload]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Telegram API request failed: '.$e->getMessage());

            return false;
        } catch (\Exception $e) {
            Log::error('Unexpected Telegram log error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Test the connection
     */
    public function test(): array|bool
    {
        return $this->message('Telegram connection test successful!');
    }

    /**
     * Get bot information
     */
    public function getBotInfo(): array|bool
    {
        try {
            $response = $this->client->get(
                'https://api.telegram.org/bot'.$this->botToken.'/getMe'
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Failed to get bot info: '.$e->getMessage());

            return false;
        }
    }
}
