<?php

namespace Uzhlaravel\Telegramlogs\Commands;

use Illuminate\Console\Command;
use Uzhlaravel\Telegramlogs\Facades\TelegramActivity;
use Uzhlaravel\Telegramlogs\Facades\Telegramlogs;
use Uzhlaravel\Telegramlogs\TelegramMessage;

class TelegramlogsCommand extends Command
{
    protected $signature = 'telegramlogs:test
                            {--message= : Custom test message to send}
                            {--level= : Log level (debug, info, notice, warning, error, critical, alert, emergency)}
                            {--list : List available log levels}
                            {--config : Show current configuration}
                            {--activity : Send a test activity log notification}';

    protected $description = 'Test and manage the Telegram Logs Monitor integration';

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

        if ($this->option('activity')) {
            return $this->sendTestActivity();
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
        $token = config('telegramlogs.bot_token');
        $env = config('telegramlogs.environments', 'production');
        $currentEnv = app()->environment();
        $active = app()->make(TelegramMessage::class)->isActiveInCurrentEnvironment();

        $this->info('Current Telegram Logs Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Bot Token', $token ? '****'.substr($token, -4) : 'Not set'],
                ['Chat ID', config('telegramlogs.chat_id') ?? 'Not set'],
                ['Topic ID', config('telegramlogs.topic_id') ?? 'Not set'],
                ['Log Level', config('telegramlogs.level', 'critical')],
                ['Active Environments', $env],
                ['Current Environment', $currentEnv],
                ['Notifications Active', $active ? '✅ Yes' : '❌ No (env not in list)'],
                ['Activity Log', config('telegramlogs.activity_log.enabled') ? '✅ Enabled' : '❌ Disabled'],
                ['Activity Events', implode(', ', config('telegramlogs.activity_log.events', []))],
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
            $this->line('Use --list to see available levels');

            return self::FAILURE;
        }

        $env = app()->environment();
        $allowed = config('telegramlogs.environments', 'production');
        $isActive = app()->make(TelegramMessage::class)->isActiveInCurrentEnvironment();

        if (! $isActive) {
            $this->warn("Notifications are disabled for the '{$env}' environment.");
            $this->warn("TELEGRAM_ENVIRONMENTS is set to '{$allowed}'.");
            $this->warn('Set TELEGRAM_ENVIRONMENTS=* in .env to enable in all environments.');

            return self::FAILURE;
        }

        $this->info('Sending test message to Telegram...');
        $this->line("Level: {$level}");
        $this->line("Message: {$message}");

        try {
            Telegramlogs::log($level, $message, [
                'command' => 'telegramlogs:test',
                'environment' => $env,
                'timestamp' => now()->toDateTimeString(),
            ]);

            $this->newLine();
            $this->info('Message sent successfully!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('Failed to send message: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function sendTestActivity(): int
    {
        $isActive = app()->make(TelegramMessage::class)->isActiveInCurrentEnvironment();

        if (! $isActive) {
            $env = app()->environment();
            $allowed = config('telegramlogs.environments', 'production');
            $this->warn("Notifications are disabled for the '{$env}' environment (TELEGRAM_ENVIRONMENTS={$allowed}).");

            return self::FAILURE;
        }

        if (! config('telegramlogs.activity_log.enabled', false)) {
            $this->warn('Activity log is disabled. Set TELEGRAM_ACTIVITY_LOG=true in .env to enable it.');

            return self::FAILURE;
        }

        $this->info('Sending test activity notification...');

        $sent = TelegramActivity::withProperties([
            'trigger' => 'telegramlogs:test --activity',
            'time' => now()->toDateTimeString(),
        ])->event('created')->dispatch('Test activity dispatched from Artisan');

        if ($sent) {
            $this->info('Activity notification sent successfully!');

            return self::SUCCESS;
        }

        $this->error('Failed to send activity notification. Check your Telegram credentials.');

        return self::FAILURE;
    }
}
