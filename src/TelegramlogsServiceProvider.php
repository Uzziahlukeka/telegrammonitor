<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs;

use Illuminate\Support\ServiceProvider;
use Uzhlaravel\Telegramlogs\Commands\InstallTelegramLogsCommand;
use Uzhlaravel\Telegramlogs\Commands\SupportBotCommand;
use Uzhlaravel\Telegramlogs\Commands\TelegramlogsCommand;
use Uzhlaravel\Telegramlogs\Support\SupportBotHandler;

final class TelegramlogsServiceProvider extends ServiceProvider
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

        // Support bot handler — singleton so config is loaded once
        $this->app->singleton(SupportBotHandler::class, function () {
            return new SupportBotHandler;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/telegramlogs.php' => config_path('telegramlogs.php'),
        ], 'telegramlogs-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_support_tickets_table.php.stub' => database_path(
                'migrations/'.date('Y_m_d_His', mktime(0, 0, 0)).'_create_support_tickets_table.php'
            ),
            __DIR__.'/../database/migrations/create_ticket_messages_table.php.stub' => database_path(
                'migrations/'.date('Y_m_d_His', mktime(0, 0, 1)).'_create_ticket_messages_table.php'
            ),
        ], 'telegramlogs-support-migrations');

        $this->registerCommands();
        $this->addTelegramLogChannel();
        $this->loadSupportRoutes();
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TelegramlogsCommand::class,
                InstallTelegramLogsCommand::class,
                SupportBotCommand::class,
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

    protected function loadSupportRoutes(): void
    {
        // Only register the webhook route when the support bot is configured
        if (config('telegramlogs.support_bot.bot_token')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/support.php');
        }
    }
}
