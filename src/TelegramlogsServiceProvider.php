<?php

namespace Uzhlaravel\Telegramlogs;

use Illuminate\Support\ServiceProvider;
use Uzhlaravel\Telegramlogs\Commands\InstallTelegramLogsCommand;
use Uzhlaravel\Telegramlogs\Commands\TelegramlogsCommand;

class TelegramlogsServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge package config with app config
        $this->mergeConfigFrom(
            __DIR__.'/../config/telegramlogs.php',
            'telegramlogs'
        );

        // Register the TelegramMessage service
        $this->app->singleton('telegram-message', function () {
            return new TelegramMessage();
        });

        // Register the TelegramMessage class
        $this->app->singleton(TelegramMessage::class, function () {
            return new TelegramMessage();
        });

    }

    public function boot()
    {
        // Publish config file
        $this->publishes([
            __DIR__.'/../config/telegramlogs.php' => config_path('telegramlogs.php'),
        ], 'telegramlogs-config');

        // Register the command
        $this->registerCommands();

        // Add telegram channel to logging configuration
        $this->addTelegramLogChannel();
    }

    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TelegramlogsCommand::class,
                InstallTelegramLogsCommand::class,
            ]);
        }
    }

    protected function addTelegramLogChannel()
    {
        // Get current logging config
        $loggingConfig = config('logging.channels', []);

        // Add telegram channel if it doesn't exist, using the config from telegramlogs
        if (! isset($loggingConfig['telegram'])) {
            $telegramChannelConfig = config('telegramlogs.channels.telegram', [
                'driver' => 'custom',
                'via' => Telegramlogs::class,
                'level' => config('telegramlogs.level', 'critical'),
                'ignore_empty_messages' => true,
            ]);

            $loggingConfig['telegram'] = $telegramChannelConfig;

            // Update the config
            config(['logging.channels' => $loggingConfig]);
        }
    }
}
