<?php

namespace App\Services\File;

use App\DTO\AIResponseData;
use App\Models\File;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\AI\FileActionInstructions;
use App\Support\Mode;
use InvalidArgumentException;

/**
 * FR-009 "File Analysis": runs an AI action (explain/summarize/analyze/
 * help/outline) against a processed file's extracted text. Uses AIService
 * (TASK-003) through its existing public method only — no changes needed
 * to AIService for this.
 *
 * Security note (TASK-007 audit): takes an already-fetched File instance
 * rather than an ID, so — same contract as
 * ConversationService::addMessage() — ownership must be enforced by
 * whoever obtained it, always via FileService::getOwned() first. The only
 * current caller (CommandHandler::fileAction()) does this. Any future
 * caller must too; this class does not re-verify ownership itself.
 */
class FileActionService
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly AIService $aiService,
    ) {
    }

    public function run(User $user, File $file, string $action): AIResponseData
    {
        if ($file->processing_status !== 'processed') {
            throw new InvalidArgumentException(
                "File is not ready (status: {$file->processing_status})."
            );
        }

        $text = $this->fileService->getExtractedText($file);

        if ($text === null) {
            throw new InvalidArgumentException('No extracted text available for this file.');
        }

        $instruction = FileActionInstructions::forAction($action);

        return $this->aiService->respond(
            user: $user,
            mode: Mode::General->value,
            userInput: $instruction,
            conversationId: $file->conversation_id,
            extraContext: [[
                'role' => 'system',
                'content' => "File \"{$file->original_name}\" content:\n{$text}",
            ]],
        );
    }
}
