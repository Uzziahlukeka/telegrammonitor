<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor;

use Illuminate\Support\ServiceProvider;
use Uzziahlukeka\TelegramMonitor\Commands\InstallTelegramLogsCommand;
use Uzziahlukeka\TelegramMonitor\Commands\SupportBotCommand;
use Uzziahlukeka\TelegramMonitor\Commands\TelegramlogsCommand;
use Uzziahlukeka\TelegramMonitor\Support\SupportBotHandler;
use Uzziahlukeka\TelegramMonitor\WebChat\WebChatBridge;

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

        $this->app->singleton(SupportBotHandler::class, function () {
            return new SupportBotHandler;
        });

        $this->app->singleton(WebChatBridge::class, function () {
            return new WebChatBridge;
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

        $this->publishes([
            __DIR__.'/../database/migrations/create_web_chat_sessions_table.php.stub' => database_path(
                'migrations/'.date('Y_m_d_His', mktime(0, 0, 2)).'_create_web_chat_sessions_table.php'
            ),
            __DIR__.'/../database/migrations/create_web_chat_messages_table.php.stub' => database_path(
                'migrations/'.date('Y_m_d_His', mktime(0, 0, 3)).'_create_web_chat_messages_table.php'
            ),
        ], 'telegramlogs-webchat-migrations');

        $this->registerCommands();
        $this->addTelegramLogChannel();
        $this->loadSupportRoutes();
        $this->loadViews();
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
        if (BotRegistry::isConfigured('support')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/support.php');
            $this->loadRoutesFrom(__DIR__.'/../routes/webchat.php');
        }
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'telegramlogs');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/telegramlogs'),
        ], 'telegramlogs-views');
    }
}
