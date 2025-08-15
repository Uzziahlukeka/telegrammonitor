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

        try {
            // Convert LogRecord to array for JSON serialization
            $recordArray = $record->toArray();

            // Encode the record array into JSON
            $message = json_encode($recordArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            // Send the JSON message
            $this->sendMessage($message);
        } catch (GuzzleException $e) {
            // Log the error to the default logger if Telegram fails
            error_log('Failed to send Telegram log: '.$e->getMessage());
        }
    }

    protected function sendMessage(string $message): void
    {
        $url = "/bot{$this->botToken}/sendMessage";

        // Format message as JSON code block for better readability in Telegram
        $formattedMessage = "```json\n".$message."\n```";

        if ($this->splitLongMessages && strlen($formattedMessage) > $this->maxMessageLength) {
            $messages = str_split($formattedMessage, $this->maxMessageLength - 100);
            foreach ($messages as $index => $partialMessage) {
                $this->sendPartialMessage(
                    $url,
                    sprintf("(%d/%d)\n%s", $index + 1, count($messages), $partialMessage)
                );
            }
        } else {
            $this->sendPartialMessage($url, $formattedMessage);
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
