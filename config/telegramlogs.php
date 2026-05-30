<?php

declare(strict_types=1);

use Uzhlaravel\Telegramlogs\Telegramlogs;

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
    | To find your chat ID: send a message to your bot, then open:
    |   https://api.telegram.org/bot<YOUR_TOKEN>/getUpdates
    | and look for "chat":{"id": ...} in the response.
    |
    | NOTE: The bot must be added to the chat/group before it can send messages.
    |
    */
    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
|--------------------------------------------------------------------------
| Bots (single-bot by default, multi-bot when you need it)
|--------------------------------------------------------------------------
|
| By default this package runs with ONE bot: the 'default' token below is
| used for logs, direct messages, the support ticketing bot AND the web
| chat widget. You don't need to touch anything to stay single-bot.
|
| If you'd rather separate responsibilities — e.g. a quiet "logs" bot and a
| customer-facing "support" bot — just give the role its own token. Any role
| left empty automatically falls back to the 'default' bot.
|
|   default → logs channel, TelegramMessage, activity log
|   support → support ticket bot + web chat widget
|
| You may add as many custom roles as you need. Resolve any role with:
|   Uzhlaravel\Telegramlogs\BotRegistry::token('your-role')
|   TelegramMessage::forRole('your-role')->toChat('-100xxx', 'msg')
|
| Example with 3 bots:
|   TELEGRAM_BOT_TOKEN          → internal logs & monitoring
|   TELEGRAM_SUPPORT_BOT_TOKEN  → customer-facing support / web chat
|   TELEGRAM_NOTIF_BOT_TOKEN    → your own third role (notifications, etc.)
|
*/
    'bots' => [
        // Powers: log channel, TelegramMessage (default), activity log.
        'default' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
        ],

        // Powers: support ticket bot + web chat widget.
        // Leave empty → falls back to the default bot (single-bot mode).
        'support' => [
            'token' => env('TELEGRAM_SUPPORT_BOT_TOKEN'),
        ],

        // Add any extra role you need; empty = inherits default bot.
        // 'notifications' => [
        //     'token' => env('TELEGRAM_NOTIF_BOT_TOKEN'),
        // ],
    ],

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
    | the topic message ID here.
    |
    */
    'topic_message_id' => env('TELEGRAM_TOPIC_MESSAGE_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Active Environments
    |--------------------------------------------------------------------------
    |
    | Define which environments will actually send notifications to Telegram.
    | Use '*' to enable in all environments, or a comma-separated list such as
    | 'production' or 'production,staging'.
    |
    | Set TELEGRAM_ENVIRONMENTS=* in .env to enable everywhere (e.g. local dev).
    | Default is 'production' so notifications are silent during development.
    |
    */
    'environments' => env('TELEGRAM_ENVIRONMENTS', 'production'),

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
        'max_length' => 4000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    |
    | Inspired by spatie/laravel-activitylog, this section controls automatic
    | Eloquent model activity tracking sent directly to your Telegram channel.
    |
    | Add the HasTelegramActivity trait to any model to opt-in:
    |
    |   use Uzhlaravel\Telegramlogs\Traits\HasTelegramActivity;
    |
    |   class Post extends Model {
    |       use HasTelegramActivity;
    |   }
    |
    */
    'activity_log' => [
        /*
        |----------------------------------------------------------------------
        | Enable Activity Log
        |----------------------------------------------------------------------
        |
        | Master switch for the model activity tracking feature.
        |
        */
        'enabled' => env('TELEGRAM_ACTIVITY_LOG', false),

        /*
        |----------------------------------------------------------------------
        | Tracked Events
        |----------------------------------------------------------------------
        |
        | Eloquent model events to track. Possible values:
        | 'created', 'updated', 'deleted', 'restored', 'forceDeleted'
        |
        */
        'events' => ['created', 'updated', 'deleted'],

        /*
        |----------------------------------------------------------------------
        | Include Old Values on Update
        |----------------------------------------------------------------------
        |
        | When true, the previous attribute values are included in the
        | notification when a model is updated.
        |
        */
        'include_old_values' => true,

        /*
        |----------------------------------------------------------------------
        | Include New Values on Update
        |----------------------------------------------------------------------
        |
        | When true, the new/changed attribute values are sent in the
        | update notification.
        |
        */
        'include_new_values' => true,

        /*
        |----------------------------------------------------------------------
        | Log Level
        |----------------------------------------------------------------------
        |
        | The log level used when sending activity notifications.
        |
        */
        'log_level' => env('TELEGRAM_ACTIVITY_LOG_LEVEL', 'info'),
    ],

    /*
|--------------------------------------------------------------------------
| Telegram Support Bot (Ticketing System)
|--------------------------------------------------------------------------
|
| This section powers the user ↔ agent tunnel:
|   User DMs the bot → ticket created → forwarded to staff group
|   Agent replies in group → bot relays reply to user's private chat
|
| Setup: php artisan telegram:support setup
|
*/
    'support_bot' => [

        /*
        |----------------------------------------------------------------------
        | Support Bot Token (legacy alias)
        |----------------------------------------------------------------------
        |
        | Token resolution now lives in the 'bots' section above and is handled
        | by Uzhlaravel\Telegramlogs\BotRegistry, which falls back to the
        | default bot automatically. This key is kept as a legacy alias for
        | backward compatibility and is only read when 'bots.support.token'
        | is empty.
        |
        */
        'bot_token' => env('TELEGRAM_SUPPORT_BOT_TOKEN'),

        /*
        |----------------------------------------------------------------------
        | Staff Group ID
        |----------------------------------------------------------------------
        |
        | Numeric ID of the private Telegram group where agents work.
        | Supergroup IDs start with -100 (e.g. -1001234567890).
        | The bot must be an admin of this group.
        |
        */
        'group_id' => env('TELEGRAM_SUPPORT_GROUP_ID'),

        /*
        |----------------------------------------------------------------------
        | Forum Topic Mode
        |----------------------------------------------------------------------
        |
        | When enabled, the staff group MUST be a Telegram forum (Group Settings
        | → Topics). Each new ticket gets its own dedicated forum topic, so every
        | conversation is isolated and reads like a private one-to-one chat.
        | Agents simply write inside the topic — no need to reply to a message —
        | and the first agent to respond is recorded as the ticket's correspondent.
        |
        | When disabled (default), the package stays in "flat" mode: all tickets
        | share the group and agents reply to a message to route their answer.
        |
        */
        'use_topics' => env('TELEGRAM_SUPPORT_USE_TOPICS', false),

        /*
        |----------------------------------------------------------------------
        | Webhook Path
        |----------------------------------------------------------------------
        |
        | The URL path that Telegram will POST updates to.
        | Make sure this route is publicly accessible and excluded from CSRF.
        |
        */
        'webhook_path' => env('TELEGRAM_SUPPORT_WEBHOOK_PATH', '/telegram/support/webhook'),

        /*
        |----------------------------------------------------------------------
        | Webhook Secret Token
        |----------------------------------------------------------------------
        |
        | Optional random string used to validate that incoming webhook
        | requests are genuinely from Telegram (X-Telegram-Bot-Api-Secret-Token).
        | Strongly recommended in production.
        |
        */
        'webhook_secret' => env('TELEGRAM_SUPPORT_WEBHOOK_SECRET'),

        /*
        |----------------------------------------------------------------------
        | Bot Messages (customisable)
        |----------------------------------------------------------------------
        */
        'messages' => [
            'welcome' => env('TELEGRAM_SUPPORT_MSG_WELCOME'),
            'ticket_created' => env('TELEGRAM_SUPPORT_MSG_CREATED'),
            'ticket_closed' => env('TELEGRAM_SUPPORT_MSG_CLOSED'),
            'help' => env('TELEGRAM_SUPPORT_MSG_HELP'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Chat Widget (no Telegram account needed for users)
    |--------------------------------------------------------------------------
    |
    | Embeds a floating chat widget on your website. Users write in the widget,
    | messages are tunnelled to the same Telegram staff group, and agent replies
    | appear in the widget in real time (via polling).
    |
    | Inclusion in your Blade layout (before </body>):
    |   @include('telegramlogs::webchat-widget')
    |
    | OR embed via a single <script> tag:
    |   <script src="/telegram-support/widget.js"></script>
    |
    | Setup: php artisan telegram:support setup
    |
    */
    'web_chat' => [

        /*
        |----------------------------------------------------------------------
        | Max Upload Size (MB)
        |----------------------------------------------------------------------
        */
        'max_upload_mb' => env('TELEGRAM_WEBCHAT_MAX_UPLOAD_MB', 20),

        /*
        |----------------------------------------------------------------------
        | Poll Interval (ms)
        |----------------------------------------------------------------------
        |
        | How often the widget checks for new messages from agents.
        |
        */
        'poll_interval_ms' => env('TELEGRAM_WEBCHAT_POLL_MS', 3000),

        /*
        |----------------------------------------------------------------------
        | Widget Appearance & Behaviour
        |----------------------------------------------------------------------
        */
        'widget' => [
            'title' => env('TELEGRAM_WEBCHAT_TITLE', 'Support'),
            'subtitle' => env('TELEGRAM_WEBCHAT_SUBTITLE', 'Nous répondons rapidement'),
            'color' => env('TELEGRAM_WEBCHAT_COLOR', '#0088CC'),
            'placeholder' => env('TELEGRAM_WEBCHAT_PLACEHOLDER', 'Votre message...'),
            'welcome_message' => env('TELEGRAM_WEBCHAT_WELCOME', 'Bonjour ! Comment pouvons-nous vous aider ?'),
            'require_name' => env('TELEGRAM_WEBCHAT_REQUIRE_NAME', false),
        ],

        /*
        |----------------------------------------------------------------------
        | Custom Messages
        |----------------------------------------------------------------------
        */
        'messages' => [
            'session_closed' => env('TELEGRAM_WEBCHAT_MSG_CLOSED'),
        ],
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
            'via' => Telegramlogs::class,
            'level' => env('TELEGRAM_LOG_LEVEL', 'critical'),
            'ignore_empty_messages' => true,
        ],
    ],
];
