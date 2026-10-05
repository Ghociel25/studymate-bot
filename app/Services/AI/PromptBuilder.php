<?php

namespace App\Services\AI;

use App\DTO\AIRequestData;

/**
 * Assembles the ordered message list sent to a provider: system rules
 * first, then context, then the user's input. Matches AI_DESIGN.md's
 * prompt principle "System rules lebih tinggi daripada user instruction."
 *
 * Deliberately generic for TASK-003: no per-mode prompt customization here
 * — that's TASK-005's "Mode-specific prompt rules" checklist item. This
 * class gives TASK-005 a single, obvious place to extend from later
 * (pass additional system messages in via $context) rather than needing
 * to rewrite it.
 */
class PromptBuilder
{
    private const BASE_SYSTEM_RULES = <<<'TEXT'
    You are StudyMate, an AI assistant for students, speaking through Telegram.
    Keep responses clear and not overly long. Never claim to have executed
    code or performed an action that did not actually happen. If you lack
    information needed to answer correctly, ask for it instead of guessing.
    TEXT;

    /**
     * @param  array<int, array{role: string, content: string}>  $context  From ContextManagerInterface::build().
     */
    public function build(string $userInput, array $context, string $mode): AIRequestData
    {
        $messages = [
            ['role' => 'system', 'content' => self::BASE_SYSTEM_RULES],
            ...$context,
            ['role' => 'user', 'content' => $userInput],
        ];

        return new AIRequestData(
            messages: $messages,
            mode: $mode,
        );
    }
}
