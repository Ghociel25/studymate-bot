<?php

namespace App\Services\Conversation;

use App\Exceptions\ConversationAccessDeniedException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Owns all conversation/message persistence. Nothing outside this service
 * should query Conversation/Message directly when the result crosses a
 * user boundary — that's how ownership stays enforceable in one place
 * (SRS FR-010, FR-017; SECURITY.md "Authentication/Authorization").
 *
 * Wired into the live Telegram webhook flow by ModeService (TASK-005),
 * which decides when to create/continue a conversation for a chat
 * message, via this class's public methods only.
 */
class ConversationService
{
    private const VALID_ROLES = ['user', 'assistant'];

    public function startConversation(User $user, ?string $title = null, ?int $projectId = null): Conversation
    {
        return Conversation::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'title' => $title,
        ]);
    }

    /**
     * Fetch a conversation the given user owns. Throws if it doesn't
     * exist or belongs to someone else — same error either way, so
     * callers can't distinguish "not found" from "not yours".
     */
    public function getOwned(User $user, int $conversationId): Conversation
    {
        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $conversation) {
            throw new ConversationAccessDeniedException;
        }

        return $conversation;
    }

    /**
     * The user's most recently updated conversation, if any — supports
     * "continue a conversation" without requiring the caller to track an
     * ID. Returns null if the user has none yet.
     */
    public function latestForUser(User $user): ?Conversation
    {
        return Conversation::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function listForUser(User $user): Collection
    {
        return Conversation::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Append a message to a conversation. Takes a Conversation instance
     * (not an ID) so ownership is enforced by whoever obtained it —
     * always get the instance via getOwned() first.
     */
    public function addMessage(Conversation $conversation, string $role, string $content, ?array $metadata = null): Message
    {
        if (! in_array($role, self::VALID_ROLES, true)) {
            throw new InvalidArgumentException(
                "Invalid message role '{$role}'. Expected one of: ".implode(', ', self::VALID_ROLES)
            );
        }

        $message = $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);

        // Keep updated_at fresh so latestForUser() reflects recent activity.
        $conversation->touch();

        return $message;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(Conversation $conversation): Collection
    {
        return $conversation->messages()->get();
    }
}
