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
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('telegramlogs')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_telegramlogs_table')
            ->hasCommand(TelegramlogsCommand::class);
    }


    /**
     * Register the application's services.
     *
     * @return void
     */
    public function register()
    {
        // Merge the package's configuration with the application's config
        $this->mergeConfigFrom(
            __DIR__.'/../config/telegramlogs.php', 'telegramlogs'
        );

        // Register the custom Telegram logger with the application
        $this->app->singleton(Telegramlogs::class, function ($app) {
            return new Telegramlogs(
                $app['config']->get('telegramlogs.bot_token'),
                $app['config']->get('telegramlogs.chat_id'),
                $app['config']->get('telegramlogs.topic_id')
            );
        });
    }

    /**
     * Bootstrap the application's services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish the configuration file to the application's config directory
        $this->publishes([
            __DIR__.'/../config/telegramlogs.php' => config_path('telegramlogs.php'),
        ], 'config');

        // Add required environment variables to the .env file if they don't exist
        $this->addEnvVariables();

        // Dynamically add the Telegram logging channel to the Laravel logging configuration
        $this->addTelegramLogChannel();
    }

    /**
     * Add the required environment variables to the .env file if they don't exist.
     *
     * @return void
     */
    protected function addEnvVariables()
    {
        $envPath = base_path('.env');

        // Check if the .env file exists
        if (File::exists($envPath)) {
            // Read the content of the .env file
            $envContent = File::get($envPath);

            // Add the necessary environment variables if they don't exist
            $this->appendEnvVariable($envContent, 'TELEGRAM_BOT_TOKEN', 'your-bot-token-here');
            $this->appendEnvVariable($envContent, 'TELEGRAM_CHAT_ID', 'your-chat-id-here');
            $this->appendEnvVariable($envContent, 'TELEGRAM_TOPIC_ID', 'your-topic-id-here'); // Optional

            // Write the updated content back to the .env file
            File::put($envPath, $envContent);
        }
    }

    /**
     * Append an environment variable to the .env file if it's not already present.
     *
     * @param  string  $envContent
     * @param  string  $key
     * @param  string  $value
     * @return void
     */
    protected function appendEnvVariable(&$envContent, $key, $value)
    {
        // Check if the environment variable already exists
        if (! str_contains($envContent, $key)) {
            // Append the variable to the .env file content
            $envContent .= "\n$key=$value";
        }
    }

    /**
     * Add the Telegram logging channel to the logging configuration
     *
     * @return void
     */
    protected function addTelegramLogChannel()
    {
        // Check if the Telegram logging channel already exists
        $channels = config('logging.channels', []);

        // Check if the 'telegram' channel is not already added
        if (! array_key_exists('telegram', $channels)) {
            // Add the Telegram logging channel dynamically
            config(['logging.channels.telegram' => [
                'driver' => 'custom',
                'via' => Telegramlogs::class, // Custom logger class
                'level' => config('telegramlogs.channels.telegram.level', 'critical'),  // Use level from config or default to 'critical'
            ]]);
        }
    }
}
