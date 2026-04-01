<?php

namespace Uzhlaravel\Telegramlogs\Commands;

use Illuminate\Console\Command;

class InstallTelegramLogsCommand extends Command
{
    protected $signature = 'telegramlogs:install';

    protected $description = 'Interactive setup for the Telegram Logs Monitor package';

    public function handle(): int
    {
        $this->components->info('Installing Telegram Logs Monitor...');
        $this->newLine();

        // 1. Publish config
        $this->call('vendor:publish', [
            '--provider' => 'Uzhlaravel\Telegramlogs\TelegramlogsServiceProvider',
            '--tag'      => 'telegramlogs-config',
        ]);

        $this->newLine();

        // 2. Show required env vars
        $this->components->info('Add the following variables to your .env file:');
        $this->newLine();
        $this->line('  <comment>Required:</comment>');
        $this->line('  TELEGRAM_BOT_TOKEN=your_bot_token_here');
        $this->line('  TELEGRAM_CHAT_ID=your_chat_id_here');
        $this->newLine();
        $this->line('  <comment>Optional:</comment>');
        $this->line('  TELEGRAM_LOG_LEVEL=critical          # min level to send (default: critical)');
        $this->line('  TELEGRAM_TOPIC_ID=                   # forum thread ID');
        $this->line('  TELEGRAM_TIMEOUT=10                  # API timeout in seconds');
        $this->newLine();
        $this->line('  <comment>Environment control (new):</comment>');
        $this->line('  TELEGRAM_ENVIRONMENTS=production     # comma-separated list or * for all');
        $this->newLine();
        $this->line('  <comment>Activity log (new):</comment>');
        $this->line('  TELEGRAM_ACTIVITY_LOG=false          # set true to enable model activity tracking');
        $this->line('  TELEGRAM_ACTIVITY_LOG_LEVEL=info     # log level for activity notifications');
        $this->newLine();

        // 3. Optionally set as default log channel
        if ($this->confirm('Set Telegram as your default log channel?', false)) {
            $this->updateEnvVariable('LOG_CHANNEL', 'telegram');
            $this->components->info('Default log channel set to "telegram" in .env');
        }

        // 4. Optionally enable activity log
        if ($this->confirm('Enable activity log (model event tracking)?', false)) {
            $this->updateEnvVariable('TELEGRAM_ACTIVITY_LOG', 'true');
            $this->components->info('Activity log enabled in .env');
        }

        // 5. Optionally enable in all environments (useful for local dev)
        if ($this->confirm('Enable notifications in ALL environments (not just production)?', false)) {
            $this->updateEnvVariable('TELEGRAM_ENVIRONMENTS', '*');
            $this->components->info('Notifications enabled for all environments in .env');
        }

        // 6. Optionally test the configuration
        if ($this->confirm('Test your Telegram configuration now?', true)) {
            $this->call('telegramlogs:test');
        }

        $this->newLine();
        $this->components->info('Telegram Logs Monitor installed successfully!');
        $this->newLine();
        $this->line('Usage tips:');
        $this->line('  • <info>php artisan telegramlogs:test</info>          – send a test log');
        $this->line('  • <info>php artisan telegramlogs:test --activity</info> – test activity notification');
        $this->line('  • <info>php artisan telegramlogs:test --config</info>  – view current config');
        $this->newLine();
        $this->line('Add <info>HasTelegramActivity</info> trait to models for automatic event tracking:');
        $this->line('  use Uzhlaravel\Telegramlogs\Traits\HasTelegramActivity;');
        $this->newLine();

        $this->line('GitHub: https://github.com/Uzziahlukeka/telegrammonitor');

        if ($this->confirm('Open the GitHub repository?', false)) {
            match (PHP_OS_FAMILY) {
                'Darwin'  => exec('open https://github.com/Uzziahlukeka/telegrammonitor'),
                'Windows' => exec('start https://github.com/Uzziahlukeka/telegrammonitor'),
                default   => exec('xdg-open https://github.com/Uzziahlukeka/telegrammonitor'),
            };
            $this->components->info('Repository opened in your browser.');
        }

        return self::SUCCESS;
    }

    protected function updateEnvVariable(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        if (preg_match("/^{$key}=/m", $content)) {
            // Update existing line
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            // Append new line
            $content .= "\n{$key}={$value}";
        }

        file_put_contents($envPath, $content);
    }
}
