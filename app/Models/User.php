<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A local user, identified by their Telegram account.
 *
 * There is no password/email auth in this MVP (see SECURITY.md:
 * "Identitas Telegram dipetakan ke local user"). If an admin/web auth
 * surface is needed later, that should be a separate, documented decision
 * rather than reusing this model's identity fields.
 *
 * `active_project_id` added in TASK-006 for FR-012 "Active Project".
 */
#[Fillable(['telegram_id', 'username', 'first_name', 'last_name', 'active_project_id'])]
class User extends Model
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            // Fixes a real bug (TASK-007 audit): without this cast,
            // active_project_id's PHP type can vary by DB driver, which
            // broke the strict comparison ($project->id === $activeId)
            // in CommandHandler::listProjects() — the active-project
            // marker could silently fail to show.
            'active_project_id' => 'integer',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Security note (TASK-007 audit): scoped to user_id as defense-in-depth
     * — same reasoning as ProjectService::getActive(). This relation is
     * read directly by ProjectContextManager and feeds into AI prompt
     * context, so it shouldn't trust active_project_id without re-checking
     * ownership.
     */
    public function activeProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'active_project_id')->where('user_id', $this->id);
    }
}
