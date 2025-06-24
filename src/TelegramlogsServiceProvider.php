<?php

namespace Uzhlaravel\Telegramlogs;

use Illuminate\Support\Facades\File;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Uzhlaravel\Telegramlogs\Commands\TelegramlogsCommand;

class TelegramlogsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('telegramlogs')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_telegramlogs_table')
            ->hasCommand(TelegramlogsCommand::class);
    }

    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(
            __DIR__.'/../config/telegramlogs.php', 'telegramlogs'
        );

        $this->app->singleton(Telegramlogs::class, function ($app) {
            return new Telegramlogs(
                $app['config']->get('telegramlogs.bot_token'),
                $app['config']->get('telegramlogs.chat_id'),
                $app['config']->get('telegramlogs.topic_id')
            );
        });
    }

    public function boot()
    {
        parent::boot();

        $this->publishes([
            __DIR__.'/../config/telegramlogs.php' => config_path('telegramlogs.php'),
        ], 'telegramlogs-config');

        $this->addEnvVariables();
        $this->addTelegramLogChannel();
    }

    protected function addEnvVariables()
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $envContent = File::get($envPath);
        $requiredVariables = [
            'TELEGRAM_LOGS_BOT_TOKEN' => 'your_bot_token_here',
            'TELEGRAM_LOGS_CHAT_ID' => 'your_chat_id_here',
            'TELEGRAM_LOGS_TOPIC_ID' => null, // Optional
            'TELEGRAM_LOGS_LEVEL' => 'critical',
        ];

        $changesMade = false;
        foreach ($requiredVariables as $key => $defaultValue) {
            if (! preg_match("/^{$key}=/m", $envContent)) {
                $envContent .= "\n{$key}={$defaultValue}";
                $changesMade = true;
            }
        }

        if ($changesMade) {
            File::put($envPath, trim($envContent)."\n");
        }
    }

    protected function addTelegramLogChannel()
    {
        // Merge with existing channels without overwriting
        $this->app['config']->set('logging.channels.telegram', array_merge(
            [
                'driver' => 'custom',
                'via' => Telegramlogs::class,
                'level' => env('TELEGRAM_LOGS_LEVEL', 'critical'),
            ],
            config('logging.channels.telegram', [])
        ));
    }
}
