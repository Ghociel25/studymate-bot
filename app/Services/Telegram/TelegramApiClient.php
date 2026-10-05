<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Telegram Bot API client. Uses Laravel's built-in HTTP client
 * (already a framework dependency — no new package added, per AI_RULES
 * "do not add dependencies without documenting why").
 *
 * TASK-002: sendMessage() (untouched below). TASK-007 adds getFileInfo()
 * + downloadFile(), needed to actually receive an uploaded document:
 * Telegram only gives a `file_id` in the update; the real bytes must be
 * fetched via a separate getFile + download call.
 */
class TelegramApiClient
{
    public function __construct(
        private readonly ?string $botToken = null,
    ) {
    }

    /**
     * Resolves a Telegram file_id to its downloadable file_path.
     */
    public function getFileInfo(string $fileId): ?string
    {
        $token = $this->botToken ?? config('services.telegram.bot_token');

        if (empty($token)) {
            Log::error('telegram.get_file.missing_token');

            return null;
        }

        $baseUrl = config('services.telegram.api_base_url', 'https://api.telegram.org');

        try {
            $response = Http::timeout(10)->get("{$baseUrl}/bot{$token}/getFile", ['file_id' => $fileId]);
        } catch (\Throwable $e) {
            // A connection-level failure (timeout/DNS/refused) throws
            // before any Response exists, so $response->failed() below
            // would never be reached. Never log $e->getMessage() here:
            // Guzzle/Laravel connection exception messages embed the full
            // request URL, which contains the bot token.
            Log::error('telegram.get_file.connection_error', ['exception_class' => $e::class]);

            return null;
        }

        if ($response->failed() || ! ($response->json('ok') === true)) {
            Log::warning('telegram.get_file.failed', ['status' => $response->status()]);

            return null;
        }

        return $response->json('result.file_path');
    }

    /**
     * Downloads the raw bytes of a file Telegram is hosting at $filePath
     * (as returned by getFileInfo()).
     */
    public function downloadFile(string $filePath): ?string
    {
        $token = $this->botToken ?? config('services.telegram.bot_token');

        if (empty($token)) {
            Log::error('telegram.download_file.missing_token');

            return null;
        }

        $baseUrl = config('services.telegram.api_base_url', 'https://api.telegram.org');

        try {
            $response = Http::timeout(30)->get("{$baseUrl}/file/bot{$token}/{$filePath}");
        } catch (\Throwable $e) {
            // See getFileInfo() above — same reasoning, never log
            // $e->getMessage() for this call.
            Log::error('telegram.download_file.connection_error', ['exception_class' => $e::class]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('telegram.download_file.failed', ['status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    public function sendMessage(int $chatId, string $text): bool
    {
        $token = $this->botToken ?? config('services.telegram.bot_token');

        if (empty($token)) {
            // Fail loud in logs, not to the user, and never log the token
            // itself. See SECURITY.md "Logging".
            Log::error('telegram.send_message.missing_token');

            return false;
        }

        $baseUrl = config('services.telegram.api_base_url', 'https://api.telegram.org');

        try {
            $response = Http::asJson()
                ->timeout(10)
                ->post("{$baseUrl}/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]);
        } catch (\Throwable $e) {
            // See getFileInfo() above — same reasoning, never log
            // $e->getMessage() for this call.
            Log::error('telegram.send_message.connection_error', ['chat_id' => $chatId, 'exception_class' => $e::class]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('telegram.send_message.failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }
}
