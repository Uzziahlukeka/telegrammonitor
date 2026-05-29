<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uzhlaravel\Telegramlogs\WebChat\WebChatController;

/*
|--------------------------------------------------------------------------
| Web Chat API Routes
|--------------------------------------------------------------------------
|
| These routes are consumed by the embeddable chat widget.
| Exclude them from CSRF in your middleware:
|
|   Laravel 11+ (bootstrap/app.php):
|     ->withMiddleware(fn($m) => $m->validateCsrfTokens(except: ['/telegram-support/*']))
|
|   Laravel 10 (VerifyCsrfToken::$except):
|     '/telegram-support/chat/*',
|
*/

Route::prefix('telegram-support')->group(function (): void {
    Route::post('/chat/start',   [WebChatController::class, 'start'])->name('telegram.webchat.start');
    Route::post('/chat/send',    [WebChatController::class, 'send'])->name('telegram.webchat.send');
    Route::post('/chat/upload',  [WebChatController::class, 'upload'])->name('telegram.webchat.upload');
    Route::get('/chat/messages', [WebChatController::class, 'messages'])->name('telegram.webchat.messages');
    Route::get('/chat/file/{id}', [WebChatController::class, 'downloadFile'])->name('telegram.webchat.file');
    Route::get('/widget.js',     [WebChatController::class, 'widgetJs'])->name('telegram.webchat.js');
});
