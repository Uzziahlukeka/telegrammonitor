<?php

namespace Uzhlaravel\Telegramlogs\Commands;

use Illuminate\Console\Command;

class InstallTelegramLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegramlogs:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Telegram Logs Monitor package';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Installing Telegram Logs Monitor package...');

        // 1. Publish configuration
        $this->call('vendor:publish', [
            '--provider' => 'Uzhlaravel\Telegramlogs\TelegramlogsServiceProvider',
            '--tag' => 'telegramlogs-config',
        ]);

        // 2. Display setup instructions
        $this->info('Please configure these environment variables in your .env file:');
        $this->line('TELEGRAM_BOT_TOKEN=your_bot_token_here');
        $this->line('TELEGRAM_CHAT_ID=your_chat_id_here');
        $this->line('Optional:');
        $this->line('TELEGRAM_TOPIC_ID=your_thread_id_here');
        $this->line('TELEGRAM_LOGS_LEVEL=error');

        // 3. Update logging configuration
        if ($this->confirm('Would you like to set Telegram as your default log channel?', true)) {
            $this->updateEnvVariable('LOG_CHANNEL', 'telegram');
            $this->info('Default log channel set to "telegram" in .env');
        }

        // 4. Test configuration
        if ($this->confirm('Would you like to test your Telegram configuration now?', true)) {
            $this->call('telegramlogs:test');
        }

        // 5. Ask user to star the GitHub repository
        $this->newLine();
        $this->info('🌟 If you find this package useful, please consider starring it on GitHub!');
        $this->line('GitHub Repository: https://github.com/Uzziahlukeka/telegrammonitor');

        if ($this->confirm('Would you like to open the GitHub repository now?', false)) {
            if (PHP_OS_FAMILY === 'Darwin') {
                exec('open https://github.com/Uzziahlukeka/telegrammonitor');
            } elseif (PHP_OS_FAMILY === 'Windows') {
                exec('start https://github.com/Uzziahlukeka/telegrammonitor');
            } elseif (PHP_OS_FAMILY === 'Linux') {
                exec('xdg-open https://github.com/Uzziahlukeka/telegrammonitor');
            }
            $this->info('GitHub repository opened in your browser!');
        }

        $this->info('Telegram Logs Monitor installed successfully!');
        $this->line('Remember to create a Telegram bot and get your credentials if you haven\'t already.');

        return 0;
    }

    /**
     * Update an environment variable in the .env file
     *
     * @param  string  $key
     * @param  string  $value
     */
    protected function updateEnvVariable($key, $value)
    {
        $envPath = base_path('.env');

        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            file_put_contents($envPath, $content);
        }
    }
}
