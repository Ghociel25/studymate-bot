<?php

namespace App\Services\File\Extractors;

use App\Services\File\Contracts\TextExtractorInterface;

/**
 * Real, working extractor for .txt files — no parsing needed, just
 * ensure valid UTF-8 output.
 */
class PlainTextExtractor implements TextExtractorInterface
{
    public function extract(string $rawContents): ?string
    {
        $text = mb_convert_encoding($rawContents, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        return trim($text) === '' ? null : $text;
    }
}
