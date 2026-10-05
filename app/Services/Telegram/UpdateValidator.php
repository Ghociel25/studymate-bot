<?php

namespace App\Services\Telegram;

/**
 * Validates that an incoming Telegram webhook payload has the minimum
 * shape this app knows how to handle, before anything else touches it.
 * See SECURITY.md "Input Security" — user/external input is never trusted
 * as-is.
 *
 * TASK-002: isSupportedTextMessage() for plain text `message` updates
 * (untouched below). TASK-007 adds isSupportedDocumentMessage() for file
 * uploads (`message.document`). Other update types (edited_message,
 * callback_query, etc.) are still "not yet supported".
 */
class UpdateValidator
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function isSupportedDocumentMessage(array $payload): bool
    {
        if (! isset($payload['update_id']) || ! is_int($payload['update_id'])) {
            return false;
        }

        $message = $payload['message'] ?? null;

        if (! is_array($message)) {
            return false;
        }

        $document = $message['document'] ?? null;

        if (! is_array($document)) {
            return false;
        }

        if (! isset($document['file_id'], $document['file_name']) || ! is_string($document['file_id']) || ! is_string($document['file_name'])) {
            return false;
        }

        if (! isset($message['chat']['id']) || ! isset($message['from']['id'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isSupportedTextMessage(array $payload): bool
    {
        if (! isset($payload['update_id']) || ! is_int($payload['update_id'])) {
            return false;
        }

        $message = $payload['message'] ?? null;

        if (! is_array($message)) {
            return false;
        }

        if (! isset($message['text']) || ! is_string($message['text'])) {
            return false;
        }

        if (! isset($message['chat']['id']) || ! isset($message['from']['id'])) {
            return false;
        }

        return true;
    }
}
