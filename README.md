# Telegram Logs Monitor for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)
![GitHub Tests Action Status](https://github.com/Uzziahlukeka/telegrammonitor/actions/workflows/run-tests.yml/badge.svg)
![Code style](https://github.com/Uzziahlukeka/telegrammonitor/actions/workflows/fix-php-code-style-issues.yml/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)
[![License](https://img.shields.io/packagist/l/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)

---

A comprehensive Laravel package that seamlessly integrates Telegram notifications into your application's logging system.  
Monitor critical events, debug issues, and stay informed about your application's health in real-time through Telegram channels or groups.

Supercharge your Laravel application monitoring with real-time Telegram messages from a form or external source.

---

## Table of Contents

- [Introduction](#introduction)
    - [Why Use Telegram Logs Monitor?](#why-use-telegram-logs-monitor)
    - [why use telegram messages ?](#why-use-telegram-messages-)
    - [Core Features](#core-features)
- [Installation](#installation)
    - [Prerequisites](#prerequisites)
    - [Quick Installation](#quick-installation)
    - [Automated Setup](#automated-setup)
    - [Manual Installation](#manual-installation)
    - [Log Channel Setup](#log-channel-setup)
    - [Configuration](#configuration)
        - [Environment Variables](#environment-variables)
        - [Reference](#reference)
- [Basic Usage](#basic-usage)
    - [LOGS](#logs)
    - [Standard Logging](#standard-logging)
    - [Command Line Utilities](#command-line-utilities)
    - [Output Formatting](#output-formatting)
    - [Supported Log Levels](#supported-log-levels)
    - [Direct Messaging Interface](#direct-messaging-interface)
- [Getting Telegram Credentials](#getting-telegram-credentials)
    - [1. Create a Telegram Bot](#1-create-a-telegram-bot)
    - [2. Get Chat ID](#2-get-chat-id)
    - [3. Forum Topics (optional)](#3-forum-topics-optional)
- [Security Best Practices](#security-best-practices)
- [Contributing](#contributing)
    - [Development Setup](#development-setup)
- [Support](#support)
- [License](#license)
- [Attribution](#attribution)

---

## Introduction

Telegram Logs Monitor is a comprehensive Laravel package that seamlessly integrates Telegram notifications into your application's logging system. Monitor critical events, debug issues, and stay informed about your application's health in real-time through Telegram channels.

### Why Use Telegram Logs Monitor?

- **Real-time Monitoring** → Instant delivery of critical notifications to your Telegram channels
- **Comprehensive Coverage** → Support for all PSR-3 log levels from debug to emergency
- **Smart Formatting** → Beautiful JSON formatting with MarkdownV2 and HTML support
- **Error Resilience** → Graceful fallback when Telegram is unavailable
- **Developer Friendly** → Interactive setup and comprehensive testing tools

### why use telegram messages ?

it is made to simplify life and handle receiving messages from a form or external source as form and view it into your telegram channel.

- **Real-time Messaging** → Instant delivery of messages notifications to your Telegram channels
- **Smart Formatting** → Beautiful text format with details on the source
- **Error Resilience** → Graceful fallback when Telegram is unavailable


---

---

## Core Features

### Essential Functionality
- **Monolog Integration**
- **Direct Messaging API**
- **Real-time Notifications**
- **Interactive Setup**
- **Comprehensive Testing**

### Advanced Capabilities
- **Multi-level Logging**
- **Forum Groups Support**
- **Smart Message Handling**
- **Performance Optimized**
- **Error Resilience**

---

## Installation

### Prerequisites
- **PHP**: 8.1 or higher
- **Laravel**: 10.0 or higher
- **Telegram Bot Token**: Obtain from [BotFather](https://core.telegram.org/bots#botfather)
- **Telegram Channel/Group Chat ID**: Destination for log notifications

### Quick Installation

```php
composer require uzhlaravel/telegramlogs
```

### Automated Setup

```php
php artisan telegramlogs:install
```

This guided process will:
- Publish the configuration file
- Help set up environment variables
- Optionally configure Telegram as your default logging channel
- Test your configuration

### Manual Installation

```php
php artisan vendor:publish --tag="telegramlogs-config"
```

### Log Channel Setup

`.env` file:

```php
LOG_CHANNEL=telegram
```

## Configuration

### Environment Variables

```php
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_CHAT_ID=your_chat_id_here
TELEGRAM_TOPIC_ID=your_thread_id_here
TELEGRAM_TOPIC_MESSAGE_ID=your_message_id_here
TELEGRAM_TIMEOUT=10
TELEGRAM_LOG_LEVEL=critical
```

### Reference

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| TELEGRAM_BOT_TOKEN | Bot API token | Yes | - |
| TELEGRAM_CHAT_ID | Target chat/channel ID | Yes | - |
| TELEGRAM_TOPIC_ID | Forum topic ID | No | null |
| TELEGRAM_TOPIC_MESSAGE_ID | Forum thread ID | No | null |
| TELEGRAM_LOG_LEVEL | Minimum log level | No | critical |
| TELEGRAM_TIMEOUT | API timeout (seconds) | No | 10 |

---


## Basic Usage

---

### LOGS

---

### Standard Logging

```php
\Log::error('Payment processing failure detected');

try {
    // Application code
} catch (\Exception $e) {
    \Log::critical('API connectivity issue', [
        'exception' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}

\Log::debug('User authentication successful', [
    'user_id' => auth()->id(),
    'ip' => request()->ip()
]);
```

### Command Line Utilities

#### Configuration Validation

```php
php artisan telegramlogs:test
```

Options:
- `--message="Custom notification"` → Dispatch custom test message
- `--level=error` → Specify log severity level
- `--list` → Display available log levels
- `--config` → Show current configuration values

Example:

```php
php artisan telegramlogs:test --message="System health check" --level=warning
```

### Output Formatting

```json
{
  "message": "Database connection failure",
  "level": "CRITICAL",
  "datetime": "2025-08-17T11:55:13.885292+00:00",
  "context": {
    "exception": "PDOException: driver not found"
  }
}
```

---

## Supported Log Levels

| Level | Description | Example Use Case |
|-------|-------------|------------------|
| Debug | Detailed diagnostic info | Query debugging, variable inspection |
| Info | General operational messages | User login, cache clearing |
| Notice | Significant normal events | New user registration, order created |
| Warning | Potential issues | API rate limiting, deprecated usage |
| Error | Runtime errors | Payment failure, external API errors |
| Critical | Critical condition alerts | Database connection failure |
| Alert | Immediate action required | Security breach detection |
| Emergency | System instability | Server down, infrastructure failure |

---

## Command Line Tools

- This is for testing and configuration purposes only.
- You can use the facade to send messages to your Telegram channel.

```php
php artisan telegramlogs:test --config //to see the config
php artisan telegramlogs:test --list // ot see the list of log levels
php artisan telegramlogs:test --message="System health check" --level=warning // to send a custom message
```
---
## Direct Messaging Interface

---

To send a message to your Telegram channel or from a form or just a simple word you want to pass , you have to use the facade for it but make sure to import it first.

```php
use TelegramLogs\Facades\Telegram; 
```

then after this you can use the facade to send a message to your channel. like this : 

```php

use Uzhlaravel\Telegramlogs\Facades\TelegramMessage;

// Basic message transmission
TelegramMessage::message('System maintenance scheduled for tonight');

// Message with options 
TelegramMessage::send('System maintenance scheduled for tonight', [
    'parse_mode' => 'HTML',
    'disable_web_page_preview' => true,
    'reply_markup' => [
        'inline_keyboard' => [
            [
                ['text' => 'Start', 'callback_data' => 'start']
            ]
        ]
    ]
]);

// Targeted chat messaging
TelegramMessage::toChat('-100123456789', 'Specific channel notification');

// Connection testing
TelegramMessage::test();

```

---

## Getting Telegram Credentials

### 1. Create a Telegram Bot
1. Open @BotFather
2. Send `/newbot` and follow instructions
3. Copy token → `TELEGRAM_BOT_TOKEN`

### 2. Get Chat ID
- **Private chats** → getUpdates API
- **Channels** → Add bot as admin → `-100xxxxxxxxx`
- **Groups** → Add bot, mention it → get `chat.id`

### 3. Forum Topics (optional)
1. Create a topic
2. Send message
3. Get `message_thread_id`

---

## Security Best Practices

- Never commit tokens
- Restrict bot access
- Store secrets in `.env`
- Audit access regularly

---

## Contributing

1. Fork repo
2. Create branch → `git checkout -b feature/amazing-feature`
3. Commit → `git commit -m 'Add some amazing feature'`
4. Push → `git push origin feature/amazing-feature`
5. Open PR

### Development Setup

```php 

git clone https://github.com/Uzziahlukeka/telegrammonitor.git
composer install
composer analyze
composer format
composer test

```

---

## Support

- Read docs + wiki
- Open issues on GitHub
- Join discussions

If helpful, consider:
- ⭐ Starring repo
-  Sharing experience
-  Reporting issues

---

## License

This package is open-sourced software licensed under the [MIT License](LICENSE.md).

---

## Attribution

- Developed by [Uzziahlukeka](https://github.com/Uzziahlukeka)
- Inspired by the Laravel community

--- 

💖 Made with love by [Uzziah Lukeka](https://github.com/Uzziahlukeka)
