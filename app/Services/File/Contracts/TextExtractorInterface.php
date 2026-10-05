<?php

namespace App\Services\File\Contracts;

/**
 * Contract for pulling plain text out of a file's raw bytes. One
 * implementation per supported type; FileService resolves which one to
 * use from the file's extension (see FileService::extractorFor()).
 *
 * Implementations must not throw for ordinary extraction failures
 * (corrupt file, unsupported structure) — return null and let the caller
 * mark the file as failed. Throwing is reserved for programmer error.
 */
interface TextExtractorInterface
{
    public function extract(string $rawContents): ?string;
}
