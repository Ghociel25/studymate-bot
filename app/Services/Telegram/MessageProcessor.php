<?php

namespace App\Services\Telegram;

use App\DTO\TelegramDocumentData;
use App\DTO\TelegramMessageData;

/**
 * Turns a raw Telegram update array (already confirmed valid by
 * UpdateValidator) into a DTO the rest of the app can work with, without
 * knowing anything about Telegram's JSON shape. process() is TASK-002's
 * original method (untouched); processDocument() is TASK-007's addition
 * for file uploads.
 */
class MessageProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function processDocument(array $payload): TelegramDocumentData
    {
        $message = $payload['message'];
        $document = $message['document'];

        return new TelegramDocumentData(
            chatId: (int) $message['chat']['id'],
            telegramUserId: (int) $message['from']['id'],
            username: $message['from']['username'] ?? null,
            firstName: $message['from']['first_name'] ?? null,
            lastName: $message['from']['last_name'] ?? null,
            fileId: $document['file_id'],
            fileName: $document['file_name'],
            mimeType: $document['mime_type'] ?? 'application/octet-stream',
            fileSize: (int) ($document['file_size'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): TelegramMessageData
    {
        $message = $payload['message'];
        $text = trim($message['text']);

        return new TelegramMessageData(
            chatId: (int) $message['chat']['id'],
            telegramUserId: (int) $message['from']['id'],
            username: $message['from']['username'] ?? null,
            firstName: $message['from']['first_name'] ?? null,
            lastName: $message['from']['last_name'] ?? null,
            text: $text,
            command: $this->extractCommand($text),
        );
    }

    private function extractCommand(string $text): ?string
    {
        if (! str_starts_with($text, '/')) {
            return null;
        }

        // First whitespace-separated token, e.g. "/start hello" -> "/start".
        $firstToken = strtolower(explode(' ', explode("\n", $text)[0])[0]);

        // Telegram commands can be suffixed with @BotUsername in group
        // chats (e.g. "/start@StudyMateBot") — strip that part.
        return explode('@', $firstToken)[0];
    }
}
