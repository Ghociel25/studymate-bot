<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the `X-Telegram-Bot-Api-Secret-Token` header Telegram sends on
 * every webhook request (configured via setWebhook's `secret_token` param).
 * This is the "secret/token verification strategy" required by
 * TELEGRAM_DESIGN.md and SECURITY.md, enforced before any controller or
 * service code runs.
 *
 * If TELEGRAM_WEBHOOK_SECRET is not configured, requests are rejected —
 * failing closed rather than silently accepting unverified traffic.
 */
class VerifyTelegramWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.telegram.webhook_secret');
        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (empty($expected) || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            // Never log the secret or the provided value, only that a
            // mismatch happened. See SECURITY.md "Logging".
            Log::warning('telegram.webhook.secret_verification_failed', [
                'ip' => $request->ip(),
            ]);

            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
