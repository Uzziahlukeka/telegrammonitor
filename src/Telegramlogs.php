<?php

namespace Uzhlaravel\Telegramlogs;

use GuzzleHttp\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;

class Telegramlogs extends AbstractProcessingHandler
{
    protected $botToken;

    protected $chatId;

    protected $topicId;

    protected $client;

    public function __construct()
    {
        $this->botToken = config('telegram-logger.bot_token');
        $this->chatId = config('telegram-logger.chat_id');
        $this->topicId = config('telegram-logger.topic_id');
        $this->client = new Client(['base_uri' => 'https://api.telegram.org']);
    }

    protected function write(LogRecord $record): void
    {
        $recordArray = is_array($record) ? $record : $record->toArray();
        $message = json_encode($recordArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $this->sendMessage($message);
    }

    protected function formatRecord(LogRecord $record): string
    {
        return sprintf(
            '[%s] %s: %s',
            $record->datetime->format('Y-m-d H:i:s'),
            $record->level->getName(),
            $record->message
        );
    }

    protected function sendMessage(string $message): void
    {
        $url = "/bot{$this->botToken}/sendMessage";
        $formattedMessage = "```json\n".$message."\n ```";

        $payload = [
            'chat_id' => $this->chatId,
            'message_thread_id' => $this->topicId,
            'text' => $formattedMessage,
            'parse_mode' => 'MarkdownV2',
        ];

        if ($this->topicId) {
            $payload['message_thread_id'] = $this->topicId;
        }

        $this->client->post($url, ['json' => $payload]);
    }

    public function __invoke(array $config)
    {
        $logger = new Logger('telegram');
        $logger->pushHandler($this);

        return $logger;
    }
}
