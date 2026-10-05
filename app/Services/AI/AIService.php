<?php

namespace App\Services\AI;

use App\DTO\AIResponseData;
use App\Models\User;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Contracts\ContextManagerInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Entry point for getting an AI response. This is the class the rest of
 * the application (Telegram layer, mode services) should depend on —
 * never AIProviderInterface or a provider SDK directly. That indirection
 * is the whole point of this task (AI_DESIGN.md "Provider Rule").
 *
 * Wired into the live Telegram webhook flow by ModeService (TASK-005)
 * and FileActionService (TASK-007), both calling respond() directly —
 * neither needed any change to this class's public contract.
 */
class AIService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly PromptBuilder $promptBuilder,
        private readonly ContextManagerInterface $contextManager,
        private readonly UsageTracker $usageTracker,
    ) {
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $extraContext  Extra
     *         system/context messages to merge in before the prompt is built
     *         (e.g. mode-specific rules from ModePromptRules). Optional and
     *         additive — existing callers that omit it behave exactly as
     *         before (TASK-003).
     */
    public function respond(User $user, string $mode, string $userInput, ?int $conversationId = null, array $extraContext = []): AIResponseData
    {
        $context = [...$this->contextManager->build($user, $conversationId), ...$extraContext];
        $request = $this->promptBuilder->build($userInput, $context, $mode);

        try {
            $response = $this->provider->complete($request);
        } catch (Throwable $e) {
            // Providers are expected to return AIResponseData::error() for
            // ordinary failures (see AIProviderInterface docblock); a thrown
            // exception means something unexpected happened. Fail safe
            // rather than let it bubble up and crash the caller.
            Log::error('ai_service.provider_threw', [
                'mode' => $mode,
                'exception' => $e->getMessage(),
            ]);

            $response = AIResponseData::error(
                provider: 'unknown',
                model: 'unknown',
                errorCode: 'provider_exception',
            );
        }

        $this->usageTracker->record($user, $mode, $response, $conversationId);

        return $response;
    }
}
