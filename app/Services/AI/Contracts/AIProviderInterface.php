<?php

namespace App\Services\AI\Contracts;

use App\DTO\AIRequestData;
use App\DTO\AIResponseData;

/**
 * Contract every AI provider adapter must implement. Business logic
 * (AIService, PromptBuilder, Telegram layer) depends only on this
 * interface, never on a provider's SDK directly — see AI_DESIGN.md
 * "Provider Rule". Swapping providers means writing a new class that
 * implements this interface and changing one config value/binding.
 *
 * Implementations must not throw for ordinary provider failures (timeouts,
 * API errors) — they should catch those and return AIResponseData::error().
 * Throwing is reserved for programmer errors (e.g. misconfiguration).
 */
interface AIProviderInterface
{
    public function complete(AIRequestData $request): AIResponseData;
}
