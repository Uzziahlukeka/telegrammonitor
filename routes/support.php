<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uzziahlukeka\TelegramMonitor\Support\SupportWebhookController;

Route::post(
    config('telegramlogs.support_bot.webhook_path', '/telegram/support/webhook'),
    SupportWebhookController::class
)->name('telegram.support.webhook');
