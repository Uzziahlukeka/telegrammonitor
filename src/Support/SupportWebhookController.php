<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SupportWebhookController extends Controller
{
    public function __construct(private readonly SupportBotHandler $handler) {}

    public function __invoke(Request $request): Response
    {
        // Verify the Telegram webhook secret if configured
        $secret = config('telegramlogs.support_bot.webhook_secret');

        if ($secret) {
            $header = $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (! hash_equals($secret, $header)) {
                return response('Unauthorized', 403);
            }
        }

        $update = $request->all();

        if (empty($update)) {
            return response('OK', 200);
        }

        try {
            $this->handler->processUpdate($update);
        } catch (Throwable $e) {
            Log::error('Telegram support webhook error: '.$e->getMessage(), [
                'update_id' => $update['update_id'] ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response('OK', 200);
    }
}
