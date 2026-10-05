<?php

namespace App\DTO;

/**
 * Normalized shape of an incoming Telegram text message, independent of
 * Telegram's raw update JSON structure. Built by
 * App\Services\Telegram\MessageProcessor.
 */
final class TelegramMessageData
{
    public function __construct(
        public readonly int $chatId,
        public readonly int $telegramUserId,
        public readonly ?string $username,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $text,
        public readonly ?string $command,
    ) {
    }
}
