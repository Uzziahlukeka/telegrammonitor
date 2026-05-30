# Telegram Monitor for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/uzziahlukeka/telegrammonitor.svg?style=flat-square)](https://packagist.org/packages/uzziahlukeka/telegrammonitor)
![GitHub Tests Action Status](https://github.com/Uzziahlukeka/telegrammonitor/actions/workflows/run-tests.yml/badge.svg)
![Code style](https://github.com/Uzziahlukeka/telegrammonitor/actions/workflows/fix-php-code-style-issues.yml/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/uzziahlukeka/telegrammonitor.svg?style=flat-square)](https://packagist.org/packages/uzziahlukeka/telegrammonitor)
[![License](https://img.shields.io/packagist/l/uzziahlukeka/telegrammonitor.svg?style=flat-square)](https://packagist.org/packages/uzziahlukeka/telegrammonitor)

---

A Laravel package that turns Telegram into your real-time ops and support hub: stream application logs, exceptions, and model activity to a channel — and run a full **support/ticketing tunnel** where customer DMs become tickets (with optional one-topic-per-ticket forum mode), agent replies are relayed back, plus an embeddable web chat widget.

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
- [Support Bot (Ticketing Tunnel)](#support-bot-ticketing-tunnel)
    - [One Topic per Ticket (forum mode)](#one-topic-per-ticket-forum-mode)
    - [Multiple Bots (logs vs support)](#multiple-bots-logs-vs-support)
    - [Quick Setup](#quick-setup)
    - [Agent Commands](#agent-commands-in-the-staff-group)
    - [Artisan Commands](#artisan-commands-1)
    - [Supported Media Types](#supported-media-types)
    - [TelegramSupport Facade](#telegramsupport-facade)
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
- **Support bot / ticketing tunnel** — user DMs become tickets forwarded to a staff group; agent replies are relayed back automatically, with full document/media support
- **Single or multiple bots** — one bot handles everything by default; split logs and support across dedicated bots whenever you need to, with automatic fallback
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
composer require uzziahlukeka/telegrammonitor
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
use Uzziahlukeka\TelegramMonitor\Facades\TelegramMessage;

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
use Uzziahlukeka\TelegramMonitor\Traits\HasTelegramActivity;

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
use Uzziahlukeka\TelegramMonitor\Traits\HasTelegramActivity;

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
use Uzziahlukeka\TelegramMonitor\Facades\TelegramActivity;

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

## Support Bot (Ticketing Tunnel)

The package includes a full **user ↔ agent support tunnel** built on top of Telegram.

```
User → DM to bot → ticket created → message forwarded to staff group
Agent → reply in group             → bot relays reply to user's private chat
```

All media types are supported: text, photos, documents, videos, voice messages, audio files, stickers.

---

### Architecture

```
[User private chat]          [Staff group]
      User ──────────────►  🎫 Ticket #0001
                             👤 Alice (@alice)
                             📅 28/05/2026 14:30
                             💬 "J'ai un problème..."
                                    │
      User ◄──────────────  Agent replies with /reply ──► bot forwards to user
```

Each ticket is stored in the database with a mapping between the group message ID and the user's Telegram ID. Agents reply using Telegram's native **Reply** feature — no slash commands needed to answer.

This is the default **flat mode**: every ticket shares the same group and agents must reply to a message to route their answer.

---

### One Topic per Ticket (forum mode)

Prefer each ticket to feel like its **own private, one-to-one conversation** on the staff side? Turn your staff group into a Telegram **forum** (Group Settings → Topics) and enable topic mode:

```env
TELEGRAM_SUPPORT_USE_TOPICS=true
```

With topic mode on:

- **One topic per ticket** — every new ticket opens its own forum topic (`#0001 · Alice (@alice)`), so conversations never get mixed together.
- **No reply needed** — agents just type inside the topic; the bot relays it straight to the customer's private chat.
- **One correspondent per ticket** — the *first agent who answers* is automatically recorded as the ticket's correspondent (shown via `/status`), and everyone sees who took it.
- **Tidy archive** — closing a ticket (`/close`) also closes its forum topic.

```
[User private chat]          [Staff group = forum]
      User ──────────────►  📂 Topic "#0001 · Alice (@alice)"
                              ├─ 🆕 Nouveau ticket / 💬 "J'ai un problème..."
                              ├─ 👤 Bob a pris en charge le ticket #0001
      User ◄──────────────   └─ Bob types here ──► relayed to the user
```

Everything is **fully backward compatible**: leave `TELEGRAM_SUPPORT_USE_TOPICS` unset (or `false`) to keep the classic flat, reply-based behaviour. The web chat widget always stays reply-based, even when ticket topics are enabled. Topic mode also works whether the support bot shares the default bot or runs as a dedicated bot.

> The bot must be an **admin with "Manage Topics" permission** in the forum group for it to create and close topics. If topic creation fails (e.g. the group isn't a forum), the ticket gracefully falls back to flat threading.

---

### Multiple Bots (logs vs support)

By default the package runs with **one bot**: `TELEGRAM_BOT_TOKEN` powers the log channel, direct messages, the support ticket bot, and the web chat widget. You don't need to configure anything to stay single-bot.

When you'd rather keep a quiet internal **logs** bot separate from a customer-facing **support** bot, give the support role its own token:

```env
# Default bot — logs, direct messages, activity log
TELEGRAM_BOT_TOKEN=111111:AAA-logs-bot-token

# Dedicated support bot — ticketing + web chat (optional)
TELEGRAM_SUPPORT_BOT_TOKEN=222222:BBB-support-bot-token
```

Tokens are resolved per *role* in `config/telegramlogs.php`. Any role left empty transparently inherits the `default` bot:

```php
'bots' => [
    'default' => ['token' => env('TELEGRAM_BOT_TOKEN')],
    'support' => ['token' => env('TELEGRAM_SUPPORT_BOT_TOKEN')], // empty → uses default
],
```

| Role | Used by | Falls back to |
|------|---------|---------------|
| `default` | log channel, `TelegramMessage`, activity log | — |
| `support` | support ticket bot + web chat widget | `default` |

**Add your own roles** for any extra bots, then resolve their tokens anywhere:

```php
use Uzziahlukeka\TelegramMonitor\BotRegistry;

$token = BotRegistry::token('marketing');   // your custom role, falls back to default
BotRegistry::hasDedicatedBot('support');     // true only if support has its own token
BotRegistry::isSingleBotMode();              // true when every role shares the default bot
```

Inspect your current topology at any time:

```bash
php artisan telegram:support status
```

```
Bot mode: single-bot
+---------+--------------+-------------------+
| Role    | Token        | Resolution        |
+---------+--------------+-------------------+
| default | ✅ configured | —                 |
| support | ✅ configured | inherits default  |
+---------+--------------+-------------------+
```

> Using two bots? Each bot is a separate Telegram identity, so create both via @BotFather. Only the **support** bot needs a webhook and must be an admin of the staff group; the logs bot only sends messages outbound.

---

### Quick Setup

**1. Run the setup guide:**

```bash
php artisan telegram:support setup
```

**2. Add environment variables:**

```env
# Required
TELEGRAM_SUPPORT_BOT_TOKEN=123456:ABC-your-support-bot-token
TELEGRAM_SUPPORT_GROUP_ID=-1001234567890

# Strongly recommended
TELEGRAM_SUPPORT_WEBHOOK_SECRET=a-long-random-secret-string

# Optional
TELEGRAM_SUPPORT_WEBHOOK_PATH=/telegram/support/webhook

# Optional — one topic per ticket (staff group must be a forum)
TELEGRAM_SUPPORT_USE_TOPICS=true
```

**3. Publish and run migrations:**

```bash
php artisan vendor:publish --tag=telegramlogs-support-migrations
php artisan migrate
```

**4. Exclude the webhook route from CSRF** (in `bootstrap/app.php` for Laravel 11+ or `VerifyCsrfToken` middleware):

```php
// Laravel 11+ — bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        '/telegram/support/webhook',
    ]);
})

// Laravel 10 — App\Http\Middleware\VerifyCsrfToken
protected $except = [
    '/telegram/support/webhook',
];
```

**5. Register the webhook:**

```bash
php artisan telegram:support webhook-set --url=https://yourapp.com/telegram/support/webhook
```

---

### Environment Variables Reference

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `TELEGRAM_SUPPORT_BOT_TOKEN` | No | falls back to `TELEGRAM_BOT_TOKEN` | Dedicated support bot token |
| `TELEGRAM_SUPPORT_GROUP_ID` | Yes | — | Staff group ID (supergroups start with `-100`) |
| `TELEGRAM_SUPPORT_USE_TOPICS` | No | `false` | One forum topic per ticket (group must be a forum) |
| `TELEGRAM_SUPPORT_WEBHOOK_PATH` | No | `/telegram/support/webhook` | Public webhook path |
| `TELEGRAM_SUPPORT_WEBHOOK_SECRET` | No | `null` | Secret validating incoming webhook requests |

---

## Security

- Store `TELEGRAM_BOT_TOKEN` only in `.env` — never commit it
- Set `TELEGRAM_SUPPORT_WEBHOOK_SECRET` in production to prevent spoofed requests
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
