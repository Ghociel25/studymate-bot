<?php

namespace App\Models;

use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata for one uploaded file, per DATABASE_DESIGN.md's `files` table.
 * Named `File` to match the table name directly; callers should alias on
 * import if it clashes with Illuminate\Http\File or
 * Symfony\Component\HttpFoundation\File\File in the same scope.
 */
#[Fillable([
    'user_id', 'project_id', 'conversation_id',
    'original_name', 'mime_type', 'size', 'storage_path', 'processing_status',
])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
