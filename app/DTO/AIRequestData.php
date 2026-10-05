<?php

namespace App\DTO;

/**
 * Provider-agnostic AI request. Built by PromptBuilder, consumed by any
 * class implementing AIProviderInterface. Nothing in this class knows
 * about a specific provider's SDK/payload shape — that translation is the
 * adapter's job (AI_DESIGN.md "Provider Rule").
 *
 * `mode` is a plain string for now (not an enum): the Mode enum/constants
 * are TASK-005's deliverable. Keeping it a string here avoids this task
 * reaching into TASK-005's scope.
 */
final class AIRequestData
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages  Ordered messages, system prompt first.
     */
    public function __construct(
        public readonly array $messages,
        public readonly string $mode,
        public readonly ?int $maxTokens = null,
    ) {
    }
}
