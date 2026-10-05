<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn in a conversation. Append-only — no `updated_at` (see
 * migration). `role` is validated at the service layer (ConversationService),
 * not the DB, to stay portable across SQLite/MySQL/PostgreSQL.
 */
#[Fillable(['conversation_id', 'role', 'content', 'metadata'])]
class Message extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
