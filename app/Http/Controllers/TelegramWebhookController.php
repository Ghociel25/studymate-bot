<?php

namespace App\Http\Controllers;

use App\Services\Telegram\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookHandler $webhookHandler,
    ) {
    }

    /**
     * Receive a Telegram update. Always returns 200 for well-formed
     * requests (even unsupported update types) so Telegram doesn't retry
     * indefinitely — per SYSTEM_DESIGN.md "Failure Strategy". Secret
     * verification already happened in VerifyTelegramWebhookSecret
     * middleware before this method runs.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        try {
            $this->webhookHandler->handle($payload);
        } catch (Throwable $e) {
            // Never leak internals to the caller (SECURITY.md); log for us.
            Log::error('telegram.webhook.processing_failed', [
                'update_id' => $payload['update_id'] ?? null,
                'exception' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
