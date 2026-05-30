<?php

declare(strict_types=1);

namespace Uzziahlukeka\TelegramMonitor\Commands;

use Illuminate\Console\Command;
use Uzziahlukeka\TelegramMonitor\BotRegistry;
use Uzziahlukeka\TelegramMonitor\Support\SupportBotHandler;
use Uzziahlukeka\TelegramMonitor\Support\SupportTicket;

final class SupportBotCommand extends Command
{
    protected $signature = 'telegram:support
        {action : setup | webhook-set | webhook-delete | status | tickets}
        {--url= : Webhook URL (for webhook-set)}
        {--secret= : Webhook secret token (for webhook-set)}
        {--limit=20 : Number of tickets to display (for tickets)}';

    protected $description = 'Manage the Telegram Support Bot (ticketing tunnel)';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'setup' => $this->runSetup(),
            'webhook-set' => $this->runWebhookSet(app(SupportBotHandler::class)),
            'webhook-delete' => $this->runWebhookDelete(app(SupportBotHandler::class)),
            'status' => $this->runStatus(app(SupportBotHandler::class)),
            'tickets' => $this->runTickets(),
            default => $this->unknownAction(),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function runSetup(): int
    {
        $this->components->info('Telegram Support Bot — Setup Guide');
        $this->newLine();

        $this->line('  <comment>Step 1 — Create the support bot:</comment>');
        $this->line('  Chat with @BotFather on Telegram and run /newbot.');
        $this->line('  Copy the token you receive.');
        $this->newLine();

        $this->line('  <comment>Step 2 — Create the staff group:</comment>');
        $this->line('  Create a private Telegram group for your support agents.');
        $this->line('  Add the bot to the group and grant it admin rights (so it can read replies).');
        $this->newLine();

        $this->line('  <comment>Step 3 — Get the group ID:</comment>');
        $this->line('  Temporarily add @userinfobot to the group, note the group ID, then remove it.');
        $this->line('  Group IDs for supergroups start with -100.');
        $this->newLine();

        $this->line('  <comment>Step 4 — Set environment variables:</comment>');
        $this->newLine();
        $this->line('  <info>Required:</info>');
        $this->line('  TELEGRAM_SUPPORT_GROUP_ID=-100123456789');
        $this->newLine();
        $this->line('  <info>Bot token — choose ONE:</info>');
        $this->line('  <comment>• Single-bot (default):</comment> reuse your logs bot — set nothing extra,');
        $this->line('    support automatically falls back to TELEGRAM_BOT_TOKEN.');
        $this->line('  <comment>• Separate support bot:</comment> create a second bot and set:');
        $this->line('    TELEGRAM_SUPPORT_BOT_TOKEN=123456:ABC-your-support-bot-token');
        $this->newLine();
        $this->line('  <info>Recommended:</info>');
        $this->line('  TELEGRAM_SUPPORT_WEBHOOK_SECRET=a-long-random-string');
        $this->newLine();
        $this->line('  <info>Optional:</info>');
        $this->line('  TELEGRAM_SUPPORT_WEBHOOK_PATH=/telegram/support/webhook');
        $this->newLine();
        $this->line('  <comment>One topic per ticket (forum mode):</comment>');
        $this->line('  Turn your staff group into a forum (Group Settings → Topics),');
        $this->line('  then set TELEGRAM_SUPPORT_USE_TOPICS=true. Each ticket then opens');
        $this->line('  its own topic — an isolated, private-feeling conversation — and the');
        $this->line('  first agent who answers becomes its dedicated correspondent.');
        $this->newLine();

        // Reflect the user's current topology.
        $mode = BotRegistry::isSingleBotMode() ? 'single-bot (support shares the default bot)' : 'multi-bot (dedicated support bot)';
        $this->line("  <comment>Current mode:</comment> <info>{$mode}</info>");
        $this->newLine();

        $this->line('  <comment>Step 5 — Publish and run migrations:</comment>');
        $this->line('  php artisan vendor:publish --tag=telegramlogs-support-migrations');
        $this->line('  php artisan migrate');
        $this->newLine();

        $this->line('  <comment>Step 6 — Register your webhook:</comment>');
        $this->line('  php artisan telegram:support webhook-set --url=https://yourapp.com/telegram/support/webhook');
        $this->newLine();

        $this->line('  <comment>Agent commands (reply in the staff group):</comment>');
        $this->line('  /status  — show ticket details');
        $this->line('  /close   — close and resolve the ticket');
        $this->newLine();

        $this->components->success('Done! Follow the steps above to get your support bot running.');

        return self::SUCCESS;
    }

    private function runWebhookSet(SupportBotHandler $handler): int
    {
        $url = $this->option('url')
            ?? $this->ask('Webhook URL (e.g. https://yourapp.com/telegram/support/webhook)');

        if (! $url) {
            $this->components->error('Webhook URL is required.');

            return self::FAILURE;
        }

        $secret = $this->option('secret')
            ?? config('telegramlogs.support_bot.webhook_secret');

        $this->components->info("Setting webhook → {$url}");

        $result = $handler->setWebhook($url, $secret ?: null);

        if ($result['ok'] ?? false) {
            $this->components->success('Webhook registered successfully!');

            return self::SUCCESS;
        }

        $this->components->error('Failed: '.($result['description'] ?? 'unknown error'));

        return self::FAILURE;
    }

    private function runWebhookDelete(SupportBotHandler $handler): int
    {
        $this->components->info('Removing webhook...');

        $result = $handler->deleteWebhook();

        if ($result['ok'] ?? false) {
            $this->components->success('Webhook removed.');

            return self::SUCCESS;
        }

        $this->components->error('Failed: '.($result['description'] ?? 'unknown error'));

        return self::FAILURE;
    }

    private function runStatus(SupportBotHandler $handler): int
    {
        // Show the bot topology first (single vs multi-bot).
        $mode = BotRegistry::isSingleBotMode() ? 'single-bot' : 'multi-bot';
        $this->components->info("Bot mode: {$mode}");

        $rows = [];
        foreach (BotRegistry::roles() as $role) {
            $configured = BotRegistry::isConfigured($role);
            $dedicated = BotRegistry::hasDedicatedBot($role);
            $rows[] = [
                $role,
                $configured ? '✅ configured' : '⚠️ missing',
                $role === 'default'
                    ? '—'
                    : ($dedicated ? 'own bot' : 'inherits default'),
            ];
        }
        $this->table(['Role', 'Token', 'Resolution'], $rows);
        $this->newLine();

        $result = $handler->getBotInfo();

        if (! ($result['ok'] ?? false)) {
            $this->components->error('Cannot reach Telegram API. Check the support bot token (TELEGRAM_SUPPORT_BOT_TOKEN or TELEGRAM_BOT_TOKEN).');

            return self::FAILURE;
        }

        $bot = $result['result'];

        $this->components->info('Support bot connected!');
        $this->table(['Property', 'Value'], [
            ['Bot name', $bot['first_name'] ?? 'N/A'],
            ['Bot username', '@'.($bot['username'] ?? 'N/A')],
            ['Bot ID', (string) ($bot['id'] ?? 'N/A')],
            ['Uses dedicated bot', BotRegistry::hasDedicatedBot('support') ? 'yes' : 'no (shares default)'],
            ['Support group', config('telegramlogs.support_bot.group_id', '(not set)')],
            ['Topic mode', config('telegramlogs.support_bot.use_topics') ? '✅ one topic per ticket' : 'flat (shared group)'],
            ['Webhook path', config('telegramlogs.support_bot.webhook_path', '/telegram/support/webhook')],
            ['Webhook secret', config('telegramlogs.support_bot.webhook_secret') ? '✅ set' : '⚠️ not set'],
        ]);

        $openCount = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
        $closedCount = SupportTicket::where('status', 'closed')->count();
        $this->newLine();
        $this->line("  Tickets open/in-progress : <info>{$openCount}</info>");
        $this->line("  Tickets closed           : <info>{$closedCount}</info>");

        return self::SUCCESS;
    }

    private function runTickets(): int
    {
        $limit = (int) $this->option('limit');

        $tickets = SupportTicket::withCount('messages')
            ->latest()
            ->limit($limit)
            ->get();

        if ($tickets->isEmpty()) {
            $this->components->info('No tickets found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Ticket', 'User', 'Username', 'Status', 'Messages', 'Created'],
            $tickets->map(fn (SupportTicket $t) => [
                $t->ticket_tag,
                $t->display_name,
                $t->username ? "@{$t->username}" : '—',
                $t->status,
                $t->messages_count,
                $t->created_at->format('d/m/Y H:i'),
            ])->toArray()
        );

        return self::SUCCESS;
    }

    private function unknownAction(): int
    {
        $this->components->error("Unknown action: {$this->argument('action')}");
        $this->line('Available: setup | webhook-set | webhook-delete | status | tickets');

        return self::FAILURE;
    }
}
