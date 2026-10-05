<?php

namespace App\DTO;

/**
 * Provider-agnostic AI response. Every AIProviderInterface implementation
 * must return one of these, regardless of how the underlying provider
 * shapes its own response — this is what keeps the rest of the app
 * decoupled from any specific provider SDK.
 *
 * Carries usage/error metadata (TASK-003 checklist item), which
 * UsageTracker persists to the `ai_requests` table.
 */
final class AIResponseData
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $content,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?string $errorCode = null,
    ) {
    }

    public static function ok(
        string $content,
        string $provider,
        string $model,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
    ): self {
        return new self(
            success: true,
            content: $content,
            provider: $provider,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    public static function error(
        string $provider,
        string $model,
        string $errorCode,
    ): self {
        return new self(
            success: false,
            content: null,
            provider: $provider,
            model: $model,
            errorCode: $errorCode,
        );
    }
}
