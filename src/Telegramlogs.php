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

    /**
     * Check whether the current environment is in the allowed list.
     */
    protected function isActiveInCurrentEnvironment(): bool
    {
        $environments = config('telegramlogs.environments', 'production');

        if ($environments === '*' || $environments === null) {
            return true;
        }

        $allowed = array_map('trim', explode(',', (string) $environments));

        return in_array(app()->environment(), $allowed, true);
    }

    protected function write(LogRecord $record): void
    {
        if (! $this->isActiveInCurrentEnvironment()) {
            return;
        }

        if ($this->ignoreEmptyMessages && empty($record->message)) {
            return;
        }

        try {
            $message = $this->formatRecord($record);
            $this->sendMessage($message);
        } catch (GuzzleException $e) {
            error_log('Failed to send Telegram log: '.$e->getMessage());
        }
    }

    /**
     * Format the log record into a human-readable message for Telegram.
     */
    protected function formatRecord(LogRecord $record): string
    {
        $includeContext = config('telegramlogs.formatting.include_context', true);
        $includeTrace = config('telegramlogs.formatting.include_stack_trace', true);

        $levelEmoji = $this->levelEmoji($record->level->getName());
        $env = app()->environment();
        $appName = config('app.name', 'Laravel');

        $lines = [
            "{$levelEmoji} *{$record->level->getName()}* — {$appName} `[{$env}]`",
            '',
            $record->message,
        ];

        $context = $record->context;

        // Extract exception separately for stack trace
        $exception = $context['exception'] ?? null;
        if ($exception instanceof \Throwable) {
            unset($context['exception']);
            $lines[] = '';
            $lines[] = "📍 `{$exception->getFile()}:{$exception->getLine()}`";
            $lines[] = '`'.$exception->getMessage().'`';
            if ($includeTrace) {
                $trace = array_slice(explode("\n", $exception->getTraceAsString()), 0, 8);
                $lines[] = '';
                $lines[] = '```';
                $lines[] = implode("\n", $trace);
                $lines[] = '```';
            }
        }

        if ($includeContext && ! empty($context)) {
            $lines[] = '';
            $lines[] = '*Context:*';
            $lines[] = '```json';
            $lines[] = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $lines[] = '```';
        }

        $lines[] = '';
        $lines[] = '🕐 `'.$record->datetime->format('Y-m-d H:i:s T').'`';

        return $this->escapeMarkdownV2(implode("\n", $lines));
    }

    /**
     * Return an emoji for the given log level name.
     */
    protected function levelEmoji(string $level): string
    {
        return match (strtolower($level)) {
            'emergency' => '🚨',
            'alert' => '🔴',
            'critical' => '💥',
            'error' => '❌',
            'warning' => '⚠️',
            'notice' => '📢',
            'info' => 'ℹ️',
            'debug' => '🐛',
            default => '📋',
        };
    }

    /**
     * Escape special characters for Telegram MarkdownV2, leaving pre/code blocks intact.
     */
    protected function escapeMarkdownV2(string $text): string
    {
        // Split on code blocks so we only escape outside them
        $parts = preg_split('/(```[\s\S]*?```|`[^`]*`)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        foreach ($parts as $i => $part) {
            // Even indices are outside code blocks, odd are code blocks
            if ($i % 2 === 0) {
                // Escape all MarkdownV2 reserved chars outside code blocks.
                // Intentionally preserve * _ ` for inline formatting.
                $result .= preg_replace('/(?<!\\\\)([\[\]()\-~>#.+!\\\\=|{}])/', '\\\\$1', $part);
            } else {
                $result .= $part;
            }
        }

        return $result;
    }

    protected function sendMessage(string $message): void
    {
        $url = "/bot{$this->botToken}/sendMessage";

        if ($this->splitLongMessages && strlen($message) > $this->maxMessageLength) {
            $messages = str_split($message, $this->maxMessageLength - 100);
            foreach ($messages as $index => $partialMessage) {
                $this->sendPartialMessage(
                    $url,
                    sprintf("\\(%d/%d\\)\n%s", $index + 1, count($messages), $partialMessage)
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

    // Required for Laravel's custom logging driver
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('telegram');

        $handler = new self(
            $config['level'] ?? config('telegramlogs.level', Logger::DEBUG),
            $config['bubble'] ?? true,
            null,
            $config['ignore_empty_messages'] ?? true,
            $config['timeout'] ?? null,
            $config['split_long_messages'] ?? null,
            $config['max_message_length'] ?? null
        );

        $logger->pushHandler($handler);

        return $logger;
    }
}
