<?php

namespace App\Services\File;

use App\Exceptions\FileAccessDeniedException;
use App\Models\File;
use App\Models\User;
use App\Services\File\Contracts\TextExtractorInterface;
use App\Services\File\Extractors\PlainTextExtractor;
use App\Services\File\Extractors\UnimplementedExtractor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Owns the file pipeline per FILE_PROCESSING.md: validate -> store
 * (private) -> extract -> normalize -> status. Mirrors ConversationService/
 * ProjectService's ownership-enforcement pattern.
 *
 * Extracted text has no DB column (not in DATABASE_DESIGN.md's `files`
 * table) — stored as a companion file on the same private disk, read on
 * demand via getExtractedText().
 */
class FileService
{
    private const DISK = 'local';

    private const VALID_STATUSES = ['pending', 'processing', 'processed', 'failed'];

    /**
     * @throws InvalidArgumentException  if extension/size isn't allowed
     */
    public function validate(string $originalName, int $size, ?string $mimeType = null): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = config('file_processing.allowed_extensions');

        if ($extension === '' || ! array_key_exists($extension, $allowed)) {
            throw new InvalidArgumentException(
                'File type not supported. Allowed: '.implode(', ', array_keys($allowed))
            );
        }

        $maxBytes = config('file_processing.max_size_kb') * 1024;

        if ($size > $maxBytes) {
            $maxKb = config('file_processing.max_size_kb');
            throw new InvalidArgumentException("File too large. Max size: {$maxKb} KB");
        }

        // TASK-007 audit: "Jangan hanya mempercayai extension dari user."
        // Extension is still the binding allowlist check above (and the
        // app never executes file content regardless of MIME, so this
        // isn't a code-execution control) — this is a soft consistency
        // check: log a mismatch for visibility, don't hard-reject, since
        // Telegram's own MIME detection isn't fully reliable and a false
        // rejection would be its own usability bug.
        if ($mimeType !== null && $mimeType !== $allowed[$extension] && $mimeType !== 'application/octet-stream') {
            Log::warning('file_service.mime_extension_mismatch', [
                'extension' => $extension,
                'expected_mime' => $allowed[$extension],
                'actual_mime' => $mimeType,
            ]);
        }

        return $extension;
    }

    /**
     * Validates, stores privately, and processes (extracts text) a file
     * in one step. Never executes the uploaded content (SECURITY.md
     * "Jangan eksekusi file upload") — it's only ever written to disk and
     * read back as bytes/text.
     */
    public function store(
        User $user,
        string $originalName,
        string $mimeType,
        string $rawContents,
        ?int $projectId = null,
        ?int $conversationId = null,
    ): File {
        $extension = $this->validate($originalName, strlen($rawContents), $mimeType);

        $storagePath = "telegram-files/{$user->id}/".Str::uuid()->toString().".{$extension}";
        Storage::disk(self::DISK)->put($storagePath, $rawContents);

        $file = File::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'conversation_id' => $conversationId,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => strlen($rawContents),
            'storage_path' => $storagePath,
            'processing_status' => 'pending',
        ]);

        $this->process($file, $rawContents, $extension);

        return $file->fresh();
    }

    private function process(File $file, string $rawContents, string $extension): void
    {
        $this->updateStatus($file, 'processing');

        $text = $this->extractorFor($extension)->extract($rawContents);

        if ($text === null) {
            $this->updateStatus($file, 'failed');

            return;
        }

        Storage::disk(self::DISK)->put($this->extractedTextPath($file), $text);
        $this->updateStatus($file, 'processed');
    }

    public function extractorFor(string $extension): TextExtractorInterface
    {
        return match ($extension) {
            'txt' => new PlainTextExtractor,
            default => new UnimplementedExtractor, // pdf, docx — see its docblock
        };
    }

    public function updateStatus(File $file, string $status): void
    {
        if (! in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid processing status '{$status}'. Expected one of: ".implode(', ', self::VALID_STATUSES)
            );
        }

        $file->processing_status = $status;
        $file->save();
    }

    public function getExtractedText(File $file): ?string
    {
        $path = $this->extractedTextPath($file);

        return Storage::disk(self::DISK)->exists($path)
            ? Storage::disk(self::DISK)->get($path)
            : null;
    }

    private function extractedTextPath(File $file): string
    {
        return $file->storage_path.'.extracted.txt';
    }

    /**
     * Fetch a file the given user owns. Throws if it doesn't exist or
     * belongs to someone else.
     */
    public function getOwned(User $user, int $fileId): File
    {
        $file = File::where('id', $fileId)
            ->where('user_id', $user->id)
            ->first();

        if (! $file) {
            throw new FileAccessDeniedException;
        }

        return $file;
    }

    /**
     * @return Collection<int, File>
     */
    public function listForUser(User $user): Collection
    {
        return File::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }
}
