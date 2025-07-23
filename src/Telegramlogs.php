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
        ?int $timeout = null,
        ?bool $splitLongMessages = null,
        ?int $maxMessageLength = null
    ) {
        parent::__construct($level, $bubble);

        $this->botToken = config('telegramlogs.bot_token') ?? '';
        $this->chatId = config('telegramlogs.chat_id') ?? '';
        $this->topicId = config('telegramlogs.topic_id');
        $this->timeout = $timeout ?? config('telegramlogs.timeout', 10);
        $this->splitLongMessages = $splitLongMessages ?? config('telegramlogs.message.split_long_messages', true);
        $this->maxMessageLength = $maxMessageLength ?? config('telegramlogs.message.max_length', 4000);

        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.telegram.org',
            'timeout' => $this->timeout,
        ]);
        $this->ignoreEmptyMessages = $ignoreEmptyMessages;
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
        $includeContext = config('telegramlogs.formatting.include_context', true);
        $includeStackTrace = config('telegramlogs.formatting.include_stack_trace', true);
        $parseMode = config('telegramlogs.formatting.parse_mode', 'MarkdownV2');

        $context = '';
        if ($includeContext && $record->context) {
            $context = "\n\nContext:\n```json\n".
                json_encode($record->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).
                "\n```";
        }

        $extra = '';
        if ($record->extra) {
            $extra = "\n\nExtra:\n```json\n".
                json_encode($record->extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).
                "\n```";
        }

        // Add stack trace for error-level logs if enabled
        $stackTrace = '';
        if ($includeStackTrace &&
            $record->level->value >= \Monolog\Level::Error->value &&
            isset($record->context['exception'])) {
            $stackTrace = "\n\nStack Trace:\n```\n".
                $record->context['exception']->getTraceAsString().
                "\n```";
        }

        if ($parseMode === 'MarkdownV2') {
            return sprintf(
                "*[%s] %s*\n\n`%s`%s%s%s",
                $record->datetime->format('Y-m-d H:i:s'),
                strtoupper($record->level->getName()),
                $this->escapeMarkdown($record->message),
                $context,
                $extra,
                $stackTrace
            );
        } elseif ($parseMode === 'HTML') {
            return sprintf(
                "<b>[%s] %s</b>\n\n<code>%s</code>%s%s%s",
                $record->datetime->format('Y-m-d H:i:s'),
                strtoupper($record->level->getName()),
                htmlspecialchars($record->message),
                str_replace(['```json', '```'], ['<pre>', '</pre>'], $context),
                str_replace(['```json', '```'], ['<pre>', '</pre>'], $extra),
                str_replace(['```', '```'], ['<pre>', '</pre>'], $stackTrace)
            );
        } else {
            // Plain text
            return sprintf(
                "[%s] %s\n\n%s%s%s%s",
                $record->datetime->format('Y-m-d H:i:s'),
                strtoupper($record->level->getName()),
                $record->message,
                str_replace(['```json', '```', "\n"], ['', '', "\n"], $context),
                str_replace(['```json', '```', "\n"], ['', '', "\n"], $extra),
                str_replace(['```', '```'], ['', ''], $stackTrace)
            );
        }
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
        $parseMode = config('telegramlogs.formatting.parse_mode', 'MarkdownV2');

        $payload = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'disable_web_page_preview' => true,
        ];

        // Only add parse_mode if it's not null (plain text)
        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($this->topicId) {
            $payload['message_thread_id'] = $this->topicId;
        }

        $this->client->post($url, [
            'json' => $payload,
            'timeout' => $this->timeout,
        ]);
    }

    // This method is required for Laravel's custom logging driver
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('telegram');

        // Create handler with merged config values
        $handler = new self(
            $config['level'] ?? config('telegramlogs.level', Logger::DEBUG),
            $config['bubble'] ?? true,
            null, // client
            $config['ignore_empty_messages'] ?? true,
            $config['timeout'] ?? null,
            $config['split_long_messages'] ?? null,
            $config['max_message_length'] ?? null
        );

        $logger->pushHandler($handler);

        return $logger;
    }
}
