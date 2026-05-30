<?php

declare(strict_types=1);

namespace Uzhlaravel\Telegramlogs;

/**
 * Central resolver for Telegram bot tokens by role.
 *
 * By default the package runs with a SINGLE bot: every role (logs, support,
 * web chat) resolves to the same `TELEGRAM_BOT_TOKEN`.
 *
 * To split responsibilities across multiple bots, define a dedicated token
 * for a role in config('telegramlogs.bots'). Any role without its own token
 * transparently falls back to the default bot, so existing single-bot setups
 * keep working with zero changes.
 *
 * Built-in roles:
 *   - 'default' : log channel, direct messages, activity log
 *   - 'support' : support ticket bot + web chat widget
 *
 * You may add as many custom roles as you like under config('telegramlogs.bots').
 */
final class BotRegistry
{
    /**
     * Resolve the bot token for a given role, falling back to the default bot.
     */
    public static function token(string $role = 'default'): string
    {
        $token = config("telegramlogs.bots.{$role}.token");

        // Legacy key support for the support bot.
        if (empty($token) && $role === 'support') {
            $token = config('telegramlogs.support_bot.bot_token');
        }

        // Any non-default role falls back to the default bot.
        if (empty($token) && $role !== 'default') {
            $token = self::resolveDefault();
        }

        if (empty($token)) {
            $token = self::resolveDefault();
        }

        return (string) $token;
    }

    /**
     * Whether a token (its own or an inherited default) is available for a role.
     */
    public static function isConfigured(string $role = 'default'): bool
    {
        return self::token($role) !== '';
    }

    /**
     * Whether this role has its OWN dedicated bot, distinct from the default.
     * Returns false when the role merely inherits the default bot.
     */
    public static function hasDedicatedBot(string $role): bool
    {
        if ($role === 'default') {
            return self::token('default') !== '';
        }

        $explicit = config("telegramlogs.bots.{$role}.token");

        if (empty($explicit) && $role === 'support') {
            $explicit = config('telegramlogs.support_bot.bot_token');
        }

        return ! empty($explicit) && $explicit !== self::resolveDefault();
    }

    /**
     * List every role the package knows about (built-ins + custom).
     *
     * @return string[]
     */
    public static function roles(): array
    {
        $roles = array_keys((array) config('telegramlogs.bots', []));

        foreach (['default', 'support'] as $builtin) {
            if (! in_array($builtin, $roles, true)) {
                $roles[] = $builtin;
            }
        }

        return $roles;
    }

    /**
     * Are we running in single-bot mode (no role has its own dedicated token)?
     */
    public static function isSingleBotMode(): bool
    {
        foreach (self::roles() as $role) {
            if ($role !== 'default' && self::hasDedicatedBot($role)) {
                return false;
            }
        }

        return true;
    }

    private static function resolveDefault(): string
    {
        return (string) (
            config('telegramlogs.bots.default.token')
            ?? config('telegramlogs.bot_token')
            ?? ''
        );
    }
}
