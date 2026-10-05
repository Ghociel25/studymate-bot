<?php

namespace App\DTO;

final class TelegramDocumentData
{
    public function __construct(
        public readonly int $chatId,
        public readonly int $telegramUserId,
        public readonly ?string $username,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly string $fileId,
        public readonly string $fileName,
        public readonly string $mimeType,
        public readonly int $fileSize,
    ) {
    }
}
