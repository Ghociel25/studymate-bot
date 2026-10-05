<?php

namespace App\Services\AI\Providers;

use App\DTO\AIRequestData;
use App\DTO\AIResponseData;
use App\Services\AI\Contracts\AIProviderInterface;

/**
 * Deterministic provider with no external calls. Serves two purposes
 * (both from the TASK-003 checklist):
 *  1. A safe default binding before a real provider is chosen (PRD "Open
 *     Decisions" — AI provider/model not finalized yet). The app can be
 *     developed and tested end-to-end without any API key or live request.
 *  2. A fixture for automated tests (TESTING.md: "Gunakan mocked provider
 *     untuk test deterministik. Jangan bergantung pada API live").
 *
 * Never claims to have produced a real model response — the content makes
 * clear it's a mock, per AI_RULES "Do not claim code was executed when it
 * was not" / AI_DESIGN "Jangan mengarang hasil execution".
 */
class MockAIProvider implements AIProviderInterface
{
    public function complete(AIRequestData $request): AIResponseData
    {
        $lastUser = collect($request->messages)->last(fn (array $m) => $m['role'] === 'user');
        $lastUserMessage = $lastUser['content'] ?? '';

        return AIResponseData::ok(
            content: "[mock-ai-response] mode={$request->mode}: ".$lastUserMessage,
            provider: 'mock',
            model: 'mock-1',
            inputTokens: $this->estimateTokens($request->messages),
            outputTokens: 8,
        );
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function estimateTokens(array $messages): int
    {
        $chars = array_sum(array_map(fn (array $m) => strlen($m['content']), $messages));

        // Rough, provider-agnostic placeholder (~4 chars/token). Real
        // token counts come from the real provider's response once one is
        // integrated.
        return (int) ceil($chars / 4);
    }
}
