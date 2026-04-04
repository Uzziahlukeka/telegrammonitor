<?php

namespace Uzhlaravel\Telegramlogs;

use Illuminate\Support\ServiceProvider;
use Uzhlaravel\Telegramlogs\Commands\InstallTelegramLogsCommand;
use Uzhlaravel\Telegramlogs\Commands\TelegramlogsCommand;

class TelegramlogsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/telegramlogs.php',
            'telegramlogs'
        );

        // TelegramMessage — direct messaging
        $this->app->singleton('telegram-message', function () {
            return new TelegramMessage;
        });

        $this->app->singleton(TelegramMessage::class, function () {
            return new TelegramMessage;
        });

        $this->app->singleton(ActivityLogger::class, function ($app) {
            return new ActivityLogger($app->make(TelegramMessage::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/telegramlogs.php' => config_path('telegramlogs.php'),
        ], 'telegramlogs-config');

        $this->registerCommands();
        $this->addTelegramLogChannel();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TelegramlogsCommand::class,
                InstallTelegramLogsCommand::class,
            ]);
        }
    }

    protected function addTelegramLogChannel(): void
    {
        $loggingConfig = config('logging.channels', []);

        if (! isset($loggingConfig['telegram'])) {
            $loggingConfig['telegram'] = config('telegramlogs.channels.telegram', [
                'driver' => 'custom',
                'via' => Telegramlogs::class,
                'level' => config('telegramlogs.level', 'critical'),
                'ignore_empty_messages' => true,
            ]);

            config(['logging.channels' => $loggingConfig]);
        }
    }
}
