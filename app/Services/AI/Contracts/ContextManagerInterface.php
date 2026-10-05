<?php

namespace App\Services\AI\Contracts;

use App\Models\User;

/**
 * Contract for selecting relevant context (conversation history, active
 * project info) to include in a prompt. TASK-003 only defines this
 * contract, per its checklist ("Create ContextManager contract") — real
 * context selection needs the `conversations` table (TASK-004) and
 * `projects` table (TASK-006), which don't exist yet.
 *
 * AI_DESIGN.md: "Context disusun dari sumber terpercaya milik user."
 * Implementations must only return context the given $user owns.
 */
interface ContextManagerInterface
{
    /**
     * @return array<int, array{role: string, content: string}>  Extra
     *         messages to merge into the prompt, in the same shape as
     *         AIRequestData::$messages. Empty array means no context.
     */
    public function build(User $user, ?int $conversationId = null): array;
}
