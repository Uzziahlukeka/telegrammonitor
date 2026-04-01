# Telegram Logs Monitor for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)
![GitHub Tests Action Status](https://github.com/Uzziahlukeka/telegrammonitor/actions/workflows/run-tests.yml/badge.svg)
![Code style](https://github.com/Uzziahlukeka/telegrammonitor/actions/workflows/fix-php-code-style-issues.yml/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)
[![License](https://img.shields.io/packagist/l/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)

---

A Laravel package that sends your application logs, exceptions, and model activity events directly to a Telegram channel or group — in real time.

Supports **Laravel 10 → 13**, PHP 8.2+, and includes production-only mode so notifications stay silent during local development.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Environment Variables](#environment-variables)
  - [Restrict to Production Only](#restrict-to-production-only)
- [Usage](#usage)
  - [Log Channel Integration](#log-channel-integration)
  - [Direct Messaging](#direct-messaging)
  - [Activity Log](#activity-log)
    - [HasTelegramActivity Trait](#hastelegramactivity-trait)
    - [TelegramActivity Facade](#telegramactivity-facade)
- [Artisan Commands](#artisan-commands)
- [Log Levels](#log-levels)
- [Getting Telegram Credentials](#getting-telegram-credentials)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

---

## Features

- **Monolog integration** — drop-in `telegram` log channel; works with `LOG_CHANNEL=telegram` or as a stacked channel
- **Direct messaging** — send arbitrary text to any chat from anywhere in your app
- **Activity log** — track Eloquent model `created / updated / deleted` events and push them to Telegram (inspired by [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog))
- **Production-only mode** — restrict notifications to specific environments with a single env var
- **Smart formatting** — emoji-labelled MarkdownV2 messages with context, exception details, and stack traces
- **Long message splitting** — automatically splits messages that exceed Telegram's 4096-char limit
- **Forum/topic support** — route messages to specific threads in a Telegram group
- **Interactive install** — guided `telegramlogs:install` command

---

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | ^8.2 |
| Laravel | ^10.0 \| ^11.0 \| ^12.0 \| ^13.0 |

---

## Installation

```bash
composer require uzhlaravel/telegramlogs
```

Run the interactive setup wizard:

```bash
php artisan telegramlogs:install
```

The wizard will publish the config file, help you set environment variables, optionally enable activity log, and send a test message.

Or publish the config manually:

```bash
php artisan vendor:publish --tag="telegramlogs-config"
```

---

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Required
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_CHAT_ID=your_chat_id_here

# Optional
TELEGRAM_LOG_LEVEL=critical          # minimum level to forward (default: critical)
TELEGRAM_TOPIC_ID=                   # forum thread / topic ID
TELEGRAM_TIMEOUT=10                  # Telegram API timeout in seconds

# Environment control — see next section
TELEGRAM_ENVIRONMENTS=production     # default: production only

# Activity log
TELEGRAM_ACTIVITY_LOG=false          # set true to enable model event tracking
TELEGRAM_ACTIVITY_LOG_LEVEL=info     # log level for activity notifications
```

### Full Reference

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `TELEGRAM_BOT_TOKEN` | Yes | — | Bot API token from @BotFather |
| `TELEGRAM_CHAT_ID` | Yes | — | Target chat, channel, or group ID |
| `TELEGRAM_TOPIC_ID` | No | `null` | Forum topic (thread) ID |
| `TELEGRAM_TOPIC_MESSAGE_ID` | No | `null` | Forum thread message ID |
| `TELEGRAM_LOG_LEVEL` | No | `critical` | Minimum PSR-3 level to send |
| `TELEGRAM_TIMEOUT` | No | `10` | HTTP timeout in seconds |
| `TELEGRAM_ENVIRONMENTS` | No | `production` | Comma-separated env list, or `*` |
| `TELEGRAM_ACTIVITY_LOG` | No | `false` | Enable model activity tracking |
| `TELEGRAM_ACTIVITY_LOG_LEVEL` | No | `info` | Log level for activity messages |

---

### Restrict to Production Only

By default, notifications are only sent when `APP_ENV=production`. This prevents your local machine or CI from flooding your Telegram channel.

```env
# Only production (default)
TELEGRAM_ENVIRONMENTS=production

# Production and staging
TELEGRAM_ENVIRONMENTS=production,staging

# Every environment — useful for local debugging
TELEGRAM_ENVIRONMENTS=*
```

The current environment and whether notifications are active are both shown in:

```bash
php artisan telegramlogs:test --config
```

---

## Usage

### Log Channel Integration

Set Telegram as your default channel:

```env
LOG_CHANNEL=telegram
```

Or add it to a stack so critical logs go to both your file log and Telegram:

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver'   => 'stack',
        'channels' => ['daily', 'telegram'],
    ],
],
```

Use it like any Laravel logger:

```php
use Illuminate\Support\Facades\Log;

Log::error('Payment processing failure');

Log::critical('Database unreachable', [
    'connection' => 'mysql',
    'host'       => config('database.connections.mysql.host'),
]);

try {
    // ...
} catch (\Exception $e) {
    Log::error('Unexpected exception', ['exception' => $e]);
}
```

Messages arrive in Telegram formatted like this:

```
❌ ERROR — MyApp [production]

Payment processing failure

Context:
{
  "connection": "mysql"
}

🕐 2025-08-19 14:32:01 UTC
```

---

### Direct Messaging

Send arbitrary messages to Telegram without going through the logger — useful for contact forms, webhooks, or manual alerts.

```php
use Uzhlaravel\Telegramlogs\Facades\TelegramMessage;

// Simple text
TelegramMessage::message('Scheduled backup completed.');

// With Telegram API options
TelegramMessage::send('Deployment finished', [
    'parse_mode'               => 'HTML',
    'disable_web_page_preview' => true,
]);

// Send to a different chat
TelegramMessage::toChat('-100987654321', 'Alert for ops team');

// Test connectivity
TelegramMessage::test();

// Get bot information
TelegramMessage::getBotInfo();
```

---

### Activity Log

Inspired by [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog), the activity log tracks Eloquent model events and pushes a formatted notification to Telegram.

Enable it in `.env`:

```env
TELEGRAM_ACTIVITY_LOG=true
```

#### HasTelegramActivity Trait

Add the trait to any Eloquent model to automatically track its lifecycle events:

```php
use Uzhlaravel\Telegramlogs\Traits\HasTelegramActivity;

class Post extends Model
{
    use HasTelegramActivity;
}
```

On `created`, `updated`, or `deleted`, a message like the following is sent to Telegram:

```
🟢 Activity — MyApp [production]

Created Post

Subject: Post #42
Properties:
{
  "attributes": { "title": "Hello World", "status": "draft" }
}

🕐 2025-08-19 14:32:01 UTC
```

**Customise per model:**

```php
use Uzhlaravel\Telegramlogs\Traits\HasTelegramActivity;

class Order extends Model
{
    use HasTelegramActivity;

    // Track only these events for this model
    protected array $telegramActivityEvents = ['created', 'deleted'];

    // Custom description
    public function getTelegramActivityDescription(string $event): string
    {
        return ucfirst($event) . ' order #' . $this->id . ' — ' . $this->status;
    }

    // Extra properties to include
    public function getTelegramActivityProperties(string $event): array
    {
        return ['total' => $this->total, 'customer' => $this->customer->name];
    }
}
```

Global event list is controlled in `config/telegramlogs.php`:

```php
'activity_log' => [
    'events'             => ['created', 'updated', 'deleted'],
    'include_old_values' => true,   // previous values on update
    'include_new_values' => true,   // changed values on update
],
```

#### TelegramActivity Facade

For manual / one-off activity notifications, use the fluent facade:

```php
use Uzhlaravel\Telegramlogs\Facades\TelegramActivity;

TelegramActivity::performedOn($post)
    ->causedBy(auth()->user())
    ->withProperty('plan', 'pro')
    ->event('published')
    ->dispatch('Post was published');

// Simpler form
TelegramActivity::log('Nightly cleanup job finished');
```

---

## Artisan Commands

```bash
# Interactive setup
php artisan telegramlogs:install

# Send a test log message
php artisan telegramlogs:test

# Send with a custom message and level
php artisan telegramlogs:test --message="Health check OK" --level=warning

# Send a test activity notification
php artisan telegramlogs:test --activity

# Show current configuration (includes environment status)
php artisan telegramlogs:test --config

# List available log levels
php artisan telegramlogs:test --list
```

---

## Log Levels

| Level | Emoji | Use Case |
|-------|-------|----------|
| `emergency` | 🚨 | System is unusable |
| `alert` | 🔴 | Immediate action required |
| `critical` | 💥 | Critical conditions |
| `error` | ❌ | Runtime errors |
| `warning` | ⚠️ | Potential issues |
| `notice` | 📢 | Significant normal events |
| `info` | ℹ️ | General operational messages |
| `debug` | 🐛 | Detailed diagnostic information |

---

## Getting Telegram Credentials

### 1. Create a Bot

1. Open [@BotFather](https://t.me/BotFather) in Telegram
2. Send `/newbot` and follow the prompts
3. Copy the token into `TELEGRAM_BOT_TOKEN`

### 2. Get Your Chat ID

- **Channel** — add the bot as an admin; the channel username (`@mychannel`) or numeric ID (`-100xxxxxxxxx`) works
- **Group** — add the bot to the group; send a message, then call `https://api.telegram.org/bot<token>/getUpdates` to find `chat.id`
- **Private chat** — start a chat with the bot, then use `getUpdates`

### 3. Forum Topics (optional)

1. Enable Topics in your group settings
2. Create a topic and send a message
3. From `getUpdates`, copy `message_thread_id` → `TELEGRAM_TOPIC_ID`

---

## Security

- Store `TELEGRAM_BOT_TOKEN` only in `.env` — never commit it
- Restrict which commands the bot can receive (via BotFather → `/mybots → Bot Settings → Group Privacy`)
- Audit who has access to your Telegram channel regularly

---

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m 'Add my feature'`
4. Push: `git push origin feature/my-feature`
5. Open a pull request

**Development commands:**

```bash
git clone https://github.com/Uzziahlukeka/telegrammonitor.git
cd telegrammonitor
composer install
composer test        # run test suite
composer analyse     # PHPStan static analysis
composer format      # Laravel Pint code style
```

---

## License

This package is open-sourced software licensed under the [MIT License](LICENSE.md).

---

💖 Made with love by [Uzziah Lukeka](https://github.com/Uzziahlukeka)
