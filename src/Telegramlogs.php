<?php

namespace Uzhlaravel\Telegramlogs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;

class Telegramlogs extends AbstractProcessingHandler
{
    protected string $botToken;

    protected string $chatId;

    protected ?string $topicId;

    protected Client $client;

    protected bool $ignoreEmptyMessages;

    protected int $timeout;

    protected bool $splitLongMessages;

    protected int $maxMessageLength;

    public function __construct(
        $level = Logger::DEBUG,
        bool $bubble = true,
        ?Client $client = null,
        bool $ignoreEmptyMessages = true,
        int $timeout = 10,
        bool $splitLongMessages = true,
        int $maxMessageLength = 4096
    ) {
        parent::__construct($level, $bubble);

        $this->botToken = config('telegram-logger.bot_token');
        $this->chatId = config('telegram-logger.chat_id');
        $this->topicId = config('telegram-logger.topic_id');
        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.telegram.org',
            'timeout' => $timeout,
        ]);
        $this->ignoreEmptyMessages = $ignoreEmptyMessages;
        $this->timeout = $timeout;
        $this->splitLongMessages = $splitLongMessages;
        $this->maxMessageLength = $maxMessageLength;
    }

    protected function write(LogRecord $record): void
    {
        if ($this->ignoreEmptyMessages && empty($record->message)) {
            return;
        }

        $formattedMessage = $this->formatRecord($record);

        try {
            $this->sendMessage($formattedMessage);
        } catch (GuzzleException $e) {
            // Log the error to the default logger if Telegram fails
            error_log('Failed to send Telegram log: '.$e->getMessage());
        }
    }

    protected function formatRecord(LogRecord $record): string
    {
        $context = $record->context ? "\n\nContext:\n```json\n".
            json_encode($record->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).
            "\n```" : '';

        $extra = $record->extra ? "\n\nExtra:\n```json\n".
            json_encode($record->extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).
            "\n```" : '';

        return sprintf(
            "*[%s] %s*\n\n`%s`%s%s",
            $record->datetime->format('Y-m-d H:i:s'),
            strtoupper($record->level->getName()),
            $this->escapeMarkdown($record->message),
            $context,
            $extra
        );
    }

    protected function escapeMarkdown(string $text): string
    {
        $characters = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($characters as $char) {
            $text = str_replace($char, '\\'.$char, $text);
        }

        return $text;
    }

    protected function sendMessage(string $message): void
    {
        $url = "/bot{$this->botToken}/sendMessage";

        if ($this->splitLongMessages && strlen($message) > $this->maxMessageLength) {
            $messages = str_split($message, $this->maxMessageLength - 100);
            foreach ($messages as $index => $partialMessage) {
                $this->sendPartialMessage(
                    $url,
                    sprintf("(%d/%d)\n%s", $index + 1, count($messages), $partialMessage)
                );
            }
        } else {
            $this->sendPartialMessage($url, $message);
        }
    }

    protected function sendPartialMessage(string $url, string $message): void
    {
        $payload = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'MarkdownV2',
            'disable_web_page_preview' => true,
        ];

        if ($this->topicId) {
            $payload['message_thread_id'] = $this->topicId;
        }

        $this->client->post($url, [
            'json' => $payload,
            'timeout' => $this->timeout,
        ]);
    }

    public function __invoke(array $config): Logger
    {
        $logger = new Logger('telegram');
        $handler = new self(
            $config['level'] ?? Logger::DEBUG,
            $config['bubble'] ?? true,
            null,
            $config['ignore_empty_messages'] ?? true,
            $config['timeout'] ?? 10,
            $config['split_long_messages'] ?? true,
            $config['max_message_length'] ?? 4096
        );

        $logger->pushHandler($handler);

        return $logger;
    }
}
