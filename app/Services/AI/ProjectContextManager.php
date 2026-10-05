<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Services\AI\Contracts\ContextManagerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Implements FR-013 "AI request dapat menggunakan context project yang
 * relevan": if the given conversation is linked to a project, that
 * project's name/description is added as system context. Falls back to
 * the user's current active project (FR-012) if no conversation is given.
 *
 * Replaces NullContextManager (TASK-003's placeholder — see its docblock:
 * "Replace the binding with a real implementation once those land —
 * AIService itself won't need to change"). AIService was not modified to
 * make this work; only the AppServiceProvider binding changed.
 *
 * Never throws: a context lookup problem should degrade to "no context",
 * not break the AI pipeline (AI_DESIGN.md "Context disusun dari sumber
 * terpercaya milik user" — if it can't be verified as the user's, it's
 * left out, not guessed at).
 */
class ProjectContextManager implements ContextManagerInterface
{
    public function build(User $user, ?int $conversationId = null): array
    {
        $project = $conversationId !== null
            ? $this->projectFromConversation($user, $conversationId)
            : $user->activeProject;

        if ($project === null) {
            return [];
        }

        $description = $project->description ? " — {$project->description}" : '';

        return [[
            'role' => 'system',
            'content' => "User is currently working on project \"{$project->name}\"{$description}.",
        ]];
    }

    private function projectFromConversation(User $user, int $conversationId): ?Project
    {
        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        if ($conversation === null) {
            // Not the user's conversation, or doesn't exist — don't leak
            // anything, just log and return no context.
            Log::warning('project_context_manager.conversation_not_owned', [
                'conversation_id' => $conversationId,
            ]);

            return null;
        }

        return $conversation->project;
    }
}
