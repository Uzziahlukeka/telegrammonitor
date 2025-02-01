<?php

// config for Uzhlaravel/Telegramlogs
return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),
    'topic_id' => env('TELEGRAM_TOPIC_ID'), // Optional for group topics

    'channels' => [
        'telegram' => [
            'driver' => 'custom',
            'via' => Uzhlaravel\Telegramlogs\Telegramlogs::class,
            'level' => 'critical',
        ],
    ],
];
