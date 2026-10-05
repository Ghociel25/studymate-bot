<?php

namespace App\Services\AI;

use App\DTO\AIResponseData;
use App\Models\AIRequest;
use App\Models\User;

/**
 * Records usage/error metadata for every AI request (TASK-003 checklist
 * item 6), satisfying SRS NFR "Observability" without ever logging prompt
 * or response content — only metadata (tokens, provider, model, status).
 */
class UsageTracker
{
    public function record(
        User $user,
        string $mode,
        AIResponseData $response,
        ?int $conversationId = null,
    ): AIRequest {
        return AIRequest::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'mode' => $mode,
            'provider' => $response->provider,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'status' => $response->success ? 'success' : 'error',
            'error_code' => $response->errorCode,
        ]);
    }
}
