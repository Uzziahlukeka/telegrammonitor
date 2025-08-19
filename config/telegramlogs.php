<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    |
    | Your Telegram bot API token. Create a new bot by talking to @BotFather
    | on Telegram and paste the token here.
    |
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Telegram Chat ID
    |--------------------------------------------------------------------------
    |
    | The chat ID where logs will be sent. For channels use @channelname,
    | for groups use the numeric ID (with negative sign for supergroups).
    |
    */
    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Telegram Topic ID
    |--------------------------------------------------------------------------
    |
    | Optional thread ID in a forum-style group where logs will be sent.
    | Only applicable for groups that have topics enabled.
    |
    */
    'topic_id' => env('TELEGRAM_TOPIC_ID', null),

    /*
     |--------------------------------------------------------------------------
     | Telegram Topic Message ID (Optional)
     |--------------------------------------------------------------------------
     |
     | If you're using Telegram groups with topics/threads, you can specify
     | the topic message ID here. Add this to your .env file as TELEGRAM_TOPIC_ID
     |
     */
    'topic_message_id' => env('TELEGRAM_TOPIC_MESSAGE_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Default Logging Level
    |--------------------------------------------------------------------------
    |
    | The minimum log level that will be sent to Telegram. Available levels:
    | emergency, alert, critical, error, warning, notice, info, debug
    |
    */
    'level' => env('TELEGRAM_LOG_LEVEL', 'critical'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in seconds for requests to Telegram's API.
    |
    */
    'timeout' => env('TELEGRAM_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Message Formatting
    |--------------------------------------------------------------------------
    */
    'formatting' => [
        /*
        |----------------------------------------------------------------------
        | Parse Mode
        |----------------------------------------------------------------------
        |
        | Telegram message formatting style. Supported values:
        | 'MarkdownV2', 'HTML', or null for plain text
        |
        */
        'parse_mode' => 'MarkdownV2',

        /*
        |----------------------------------------------------------------------
        | Include Context
        |----------------------------------------------------------------------
        |
        | Whether to include context data with log messages.
        |
        */
        'include_context' => true,

        /*
        |----------------------------------------------------------------------
        | Include Stack Trace
        |----------------------------------------------------------------------
        |
        | Whether to include stack traces for error-level logs.
        |
        */
        'include_stack_trace' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Handling
    |--------------------------------------------------------------------------
    */
    'message' => [
        /*
        |----------------------------------------------------------------------
        | Split Long Messages
        |----------------------------------------------------------------------
        |
        | Whether to automatically split messages longer than Telegram's 4096
        | character limit into multiple messages.
        |
        */
        'split_long_messages' => true,

        /*
        |----------------------------------------------------------------------
        | Max Message Length
        |----------------------------------------------------------------------
        |
        | Maximum length for each message before splitting occurs.
        |
        */
        'max_length' => 4000,  // Slightly under 4096 for safety
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Channels Configuration
    |--------------------------------------------------------------------------
    |
    | Pre-configured logging channel for Laravel's logging system.
    |
    */
    'channels' => [
        'telegram' => [
            'driver' => 'custom',
            'via' => \Uzhlaravel\Telegramlogs\Telegramlogs::class,
            'level' => env('TELEGRAM_LOG_LEVEL', 'critical'),
            'ignore_empty_messages' => true,
        ],
    ],
];
