# Telegram Logs Monitor for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)
![GitHub Tests Action Status](https://github.com/Uzziahlukeka/telegrammonitor/actions/workflows/run-tests.yml/badge.svg)
![Code style](https://github.com/Uzziahlukeka/telegrammonitor/actions/workflows/fix-php-code-style-issues.yml/badge.svg)
[![Total Downloads](https://img.shields.io/packagist/dt/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)
[![License](https://img.shields.io/packagist/l/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)

A comprehensive Laravel package that seamlessly integrates Telegram notifications into your application's logging system. Monitor critical events, debug issues, and stay informed about your application's health in real-time through Telegram channels.
## Key Features

### Core Functionality
- **Monolog Integration** - Native Laravel logging channel support
- **Direct Messaging API** - Send custom messages programmatically
- **Real-time Notifications** - Instant delivery to Telegram channels/groups
- **Automatic Installation** - One-command setup with guided configuration
- **Configuration Testing** - Built-in commands to verify your setup

### Advanced Capabilities
- **Multi-level Logging** - Support for all PSR-3 log levels (debug to emergency)
- **Forum Groups Support** - Send messages to specific topics/threads
- **Message Formatting** - MarkdownV2 and HTML support with JSON pretty-printing
- **Smart Message Handling** - Automatic splitting for messages exceeding Telegram's limits
- **Error Resilience** - Graceful fallback when Telegram is unavailable
- **Performance Optimized** - Configurable timeouts and connection pooling

## Prerequisites

Ensure the following requirements are met before installation:

1. **Telegram Bot Token**: Obtain from [BotFather](https://core.telegram.org/bots#botfather)
2. **Telegram Channel/Group Chat ID**: Destination for log notifications
3. **Laravel Framework**: Version 8.0 or higher

## Installation

Integrate the package via Composer:

```bash
composer require uzhlaravel/telegramlogs
```

### Automated Installation Procedure

Execute the installation command for guided configuration:

```bash
php artisan telegramlogs:install
```

This automated process will:
1. Deploy the configuration file
2. Guide through environment variable setup
3. Optionally configure Telegram as the default logging channel
4. Validate configuration integrity

### Manual Installation Process

Publish the configuration file manually:

```bash
php artisan vendor:publish --tag="telegramlogs-config"
```

## Configuration

### Automated Configuration

Execute the installation command for interactive setup:

```bash
php artisan telegramlogs:install
```

### Manual Environment Configuration

Update your `.env` file with the following parameters:

```ini
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_CHAT_ID=your_chat_id_here

# Optional configuration parameters:
TELEGRAM_TOPIC_ID=your_thread_id_here
TELEGRAM_TOPIC_MESSAGE_ID=your_message_id_here
TELEGRAM_TIMEOUT=10
TELEGRAM_LOGS_LEVEL=error
```
### Environment Variables

| Variable | Description                      | Required | Default | Comments |
|----------|----------------------------------|----------|---------| ---------|
| `TELEGRAM_BOT_TOKEN` | Your bot's API token             | Yes | - |
| `TELEGRAM_CHAT_ID` | Target chat/channel ID           | Yes | - |
| `TELEGRAM_TOPIC_ID` | Forum logs topic ID (optional)   | No | `null` | for your logs topic|
| `TELEGRAM_TOPIC_MESSAGE_ID` | Forum direct topic ID (optional) | No | `null` |for your direct msg such as a form|
| `TELEGRAM_LOG_LEVEL` | Minimum log level                | No | `critical` |
| `TELEGRAM_TIMEOUT` | API request timeout              | No | `10` |


### Supported Log Levels

| Severity Level | Description |
|----------------|-------------|
| Debug | Detailed diagnostic information |
| Info | General operational messages |
| Notice | Significant normal events |
| Warning | Potential issue notifications |
| Error | Runtime error conditions |
| Critical | Critical condition alerts |
| Alert | Immediate action requirements |
| Emergency | System instability events |

### Log Channel Configuration

Set the default logging channel in your `.env` file:

```ini
LOG_CHANNEL=telegram
```

## Implementation

### Standard Logging Implementation

```php
// Error logging implementation
\Log::error('Payment processing failure detected');

// Exception handling with logging
try {
    // Application code execution
} catch (\Exception $e) {
    \Log::critical('API connectivity issue: ' . $e->getMessage());
}

// Debug information recording
\Log::debug('User authentication successful', ['user_id' => auth()->id()]);
```

### Command Line Utilities

#### Configuration Validation

```bash
php artisan telegramlogs:test
```

Available options:
- `--message="Custom notification"` - Dispatch custom test message
- `--level=error` - Specify log severity level
- `--list` - Display available log levels
- `--config` - Show current configuration values

Custom message example:
```bash
php artisan telegramlogs:test --message="System health check" --level=warning
```

#### Configuration Display

```bash
php artisan telegramlogs:test --config
```

## Output Formatting

Notifications delivered to your Telegram channel will feature structured JSON formatting:

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

![Telegram Notification Sample](img.png)

## Direct Messaging Interface

To initiate direct messaging, import the facade:

```php
use Uzhlaravel\Telegramlogs\Facades\TelegramMessage;
```

Implementation examples:

```php
// Basic message transmission
TelegramMessage::message('System notification from Laravel');

// Targeted chat messaging
TelegramMessage::toChat('alternative_chat_id', 'Specific channel message');

// Connection verification
TelegramMessage::test();
```

## 📱 Getting Telegram Credentials

### 1. Create a Telegram Bot

1. Open Telegram and search for [@BotFather](https://t.me/BotFather)
2. Send `/newbot` and follow the instructions
3. Choose a name and username for your bot
4. Copy the provided API token

### 2. Get Chat ID

For **private chats**:
1. Send a message to your bot
2. Visit: `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates`
3. Look for the `chat.id` value

For **channels**:
1. Add your bot as an administrator
2. The chat ID is usually in the format: `-100xxxxxxxxx`

For **groups**:
1. Add your bot to the group
2. Send a message mentioning the bot
3. Check the API endpoint above for the group's chat ID

### 3. Forum Topics (Optional)

For groups with topics enabled:
1. Create a topic in your group
2. Send a message to that topic
3. Use the API endpoint to find the `message_thread_id`
## Security Protocol

- Maintain bot token confidentiality
- Implement chat-specific bot access restrictions
- Review [our security policy](../../security/policy) for vulnerability disclosure procedures

## Version History

Detailed release notes available in [CHANGELOG](CHANGELOG.md).

## Contribution Guidelines

We encourage community contributions. Please review [CONTRIBUTING](CONTRIBUTING.md) for participation guidelines.

## Licensing

MIT License. Complete license details available in [LICENSE](LICENSE.md).

## Attribution

- [Uzziahlukeka](https://github.com/Uzziahlukeka/telegrammonitor)
- [Contributor List](../../contributors)
