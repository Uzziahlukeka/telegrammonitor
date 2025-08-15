# Telegram Logs Monitor for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/uzhlaravel/telegramlogs/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/uzhlaravel/telegramlogs/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/uzhlaravel/telegramlogs/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/uzhlaravel/telegramlogs/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/uzhlaravel/telegramlogs.svg?style=flat-square)](https://packagist.org/packages/uzhlaravel/telegramlogs)

Get real-time Laravel application logs directly in your Telegram channel. This package provides instant monitoring of your application's critical events through Telegram messages, with support for threaded discussions and Markdown formatting.


## Features

- 📨 Instant delivery of logs to Telegram
- 🔔 Configurable log levels (emergency to debug)
- 🧵 Support for Telegram topic threads
- ✏️ MarkdownV2 formatted messages
- 📦 Automatic splitting of long messages
- ⏱ Configurable timeout for API calls
- 🛠 Test command to verify your setup

## Installation

You can install the package via composer:

```bash
composer require uzhlaravel/telegramlogs
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="telegramlogs-config"
```

This is the contents of the published config file:

```php
return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),
    'topic_id' => env('TELEGRAM_TOPIC_ID'),
];
```

This is the contents in .env:

```php
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_CHAT_ID=your_chat_id_here
# Optional:
TELEGRAM_TOPIC_ID=your_thread_id_here
TELEGRAM_LOGS_LEVEL=error
```

## Usage

```php
$telegramlogs = new Uzhlaravel\Telegramlogs();
echo $telegramlogs->echoPhrase('Hello, Uzhlaravel!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [uzziahlukeka](https://github.com/uzhlaravel)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
