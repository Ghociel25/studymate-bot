<?php

namespace App\Services\Telegram;

use App\DTO\AIResponseData;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\AI\ModePromptRules;
use App\Services\Conversation\ConversationService;
use App\Services\Project\ProjectService;
use App\Support\Mode;

/**
 * SYSTEM_DESIGN.md "Mode Service" pipeline step: given a routed intent,
 * persists the user's message, asks AIService for a mode-aware response
 * (same AIService/pipeline for all four modes — no duplication, which is
 * this task's acceptance criterion), and persists the assistant's reply
 * if it succeeded.
 *
 * Reuses ConversationService (TASK-004) and AIService (TASK-003) through
 * their existing public methods only — neither was modified beyond
 * AIService's new optional parameter. TASK-006 adds ProjectService so a
 * newly-started conversation picks up the user's active project
 * (FR-012/013) — an existing (reused) conversation keeps whatever
 * project_id it already had, unchanged.
 */
class ModeService
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly AIService $aiService,
        private readonly ProjectService $projects,
    ) {
    }

    public function handle(User $user, Mode $mode, string $input): AIResponseData
    {
        $conversation = $this->conversations->latestForUser($user)
            ?? $this->conversations->startConversation(
                $user,
                projectId: $this->projects->getActive($user)?->id,
            );

        $this->conversations->addMessage($conversation, 'user', $input);

        $response = $this->aiService->respond(
            user: $user,
            mode: $mode->value,
            userInput: $input,
            conversationId: $conversation->id,
            extraContext: ModePromptRules::forMode($mode),
        );

        if ($response->success && $response->content !== null) {
            $this->conversations->addMessage($conversation, 'assistant', $response->content);
        }

        return $response;
    }
}
