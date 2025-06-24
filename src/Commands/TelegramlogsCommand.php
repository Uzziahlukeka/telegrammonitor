<?php

namespace Uzhlaravel\Telegramlogs\Commands;

use Illuminate\Console\Command;
use Uzhlaravel\Telegramlogs\Facades\Telegramlogs;

class TelegramlogsCommand extends Command
{
    protected $signature = 'telegramlogs:test
                            {--message= : Custom test message to send}
                            {--level= : Log level (debug, info, notice, warning, error, critical, alert, emergency)}
                            {--list : List available log levels}
                            {--config : Show current configuration}';

    protected $description = 'Test and manage Telegram logs integration';

    protected array $logLevels = [
        'debug',
        'info',
        'notice',
        'warning',
        'error',
        'critical',
        'alert',
        'emergency',
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listLogLevels();
        }

        if ($this->option('config')) {
            return $this->showConfig();
        }

        return $this->sendTestMessage();
    }

    protected function listLogLevels(): int
    {
        $this->info('Available log levels:');
        $this->newLine();

        foreach ($this->logLevels as $level) {
            $this->line("- {$level}");
        }

        return self::SUCCESS;
    }

    protected function showConfig(): int
    {
        $this->info('Current Telegram Logs Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Bot Token', config('telegram-logger.bot_token') ? '****'.substr(config('telegram-logger.bot_token'), -4) : 'Not set'],
                ['Chat ID', config('telegram-logger.chat_id') ?? 'Not set'],
                ['Topic ID', config('telegram-logger.topic_id') ?? 'Not set'],
                ['Default Level', config('telegram-logger.level', 'error')],
            ]
        );

        return self::SUCCESS;
    }

    protected function sendTestMessage(): int
    {
        $message = $this->option('message') ?? 'This is a test message from Telegramlogs command';
        $level = strtolower($this->option('level') ?? 'info');

        if (! in_array($level, $this->logLevels)) {
            $this->error("Invalid log level: {$level}");
            $this->line('Use --list option to see available levels');

            return self::FAILURE;
        }

        $this->info('Sending test message to Telegram...');
        $this->newLine();
        $this->line("Level: {$level}");
        $this->line("Message: {$message}");

        try {
            Telegramlogs::log($level, $message, [
                'command' => 'telegramlogs:test',
                'timestamp' => now()->toDateTimeString(),
            ]);

            $this->newLine();
            $this->info('Message sent successfully!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('Failed to send message:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
