<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only record of one AI provider request (usage + outcome),
 * per DATABASE_DESIGN.md. No `updated_at` — see the migration for why.
 */
#[Fillable([
    'user_id', 'conversation_id', 'mode', 'provider', 'model',
    'input_tokens', 'output_tokens', 'status', 'error_code',
])]
class AIRequest extends Model
{
    // Eloquent's default table-name guess from the class name "AIRequest"
    // would be "a_i_requests" (Str::snake() splits consecutive capitals
    // A-I), not the actual "ai_requests" table from the migration. Must
    // be explicit for any model whose name has a 2+ letter acronym.
    protected $table = 'ai_requests';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
