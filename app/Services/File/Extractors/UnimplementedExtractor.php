<?php

namespace App\Services\File\Extractors;

use App\Services\File\Contracts\TextExtractorInterface;

/**
 * Used for file types that are accepted for upload (allowlisted,
 * validated, stored privately) but whose content extraction isn't
 * implemented yet — currently .pdf and .docx. Parsing these properly
 * needs a dedicated library (e.g. smalot/pdfparser, phpoffice/phpword),
 * which hasn't been chosen/added (no Packagist access in this sandbox to
 * even verify one installs — see TASK-001's verification note — and
 * AI_RULES requires documenting any new dependency before adding it).
 *
 * Always returns null — FileService marks the file 'failed' with a clear
 * reason, never fabricates extracted content (AI_RULES: "Jangan
 * mengarang hasil execution").
 */
class UnimplementedExtractor implements TextExtractorInterface
{
    public function extract(string $rawContents): ?string
    {
        return null;
    }
}
