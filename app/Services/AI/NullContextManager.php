<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\Contracts\ContextManagerInterface;

/**
 * No-op ContextManagerInterface implementation: always returns no extra
 * context. Was the default binding from TASK-003 until TASK-006 replaced
 * it with ProjectContextManager in AppServiceProvider — that swap already
 * happened, and AIService needed zero changes for it (the point of the
 * contract). Kept here as a trivial fallback implementation (e.g. for
 * tests that explicitly want zero context), not as the active binding.
 */
class NullContextManager implements ContextManagerInterface
{
    public function build(User $user, ?int $conversationId = null): array
    {
        return [];
    }
}
