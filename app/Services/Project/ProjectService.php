<?php

namespace App\Services\Project;

use App\Exceptions\ProjectAccessDeniedException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Owns all project persistence and the "active project" state (FR-012).
 * Same ownership-enforcement pattern as ConversationService (TASK-004):
 * nothing outside this service should query Project directly when the
 * result crosses a user boundary.
 */
class ProjectService
{
    /**
     * Not enumerated in DATABASE_DESIGN.md — this implementation's own
     * reasonable choice, documented here rather than invented silently.
     */
    private const VALID_STATUSES = ['active', 'completed', 'archived'];

    public function create(User $user, string $name, ?string $description = null): Project
    {
        return Project::create([
            'user_id' => $user->id,
            'name' => $name,
            'description' => $description,
            'status' => 'active',
        ]);
    }

    /**
     * Fetch a project the given user owns. Throws if it doesn't exist or
     * belongs to someone else — same error either way.
     */
    public function getOwned(User $user, int $projectId): Project
    {
        $project = Project::where('id', $projectId)
            ->where('user_id', $user->id)
            ->first();

        if (! $project) {
            throw new ProjectAccessDeniedException;
        }

        return $project;
    }

    /**
     * @return Collection<int, Project>
     */
    public function listForUser(User $user): Collection
    {
        return Project::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();
    }

    public function update(User $user, int $projectId, array $attributes): Project
    {
        $project = $this->getOwned($user, $projectId);

        if (isset($attributes['status']) && ! in_array($attributes['status'], self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid project status '{$attributes['status']}'. Expected one of: ".implode(', ', self::VALID_STATUSES)
            );
        }

        $project->fill(array_intersect_key($attributes, array_flip(['name', 'description', 'status'])));
        $project->save();

        return $project;
    }

    public function delete(User $user, int $projectId): void
    {
        $project = $this->getOwned($user, $projectId);
        $project->delete();
        // users.active_project_id and conversations.project_id are both
        // nullOnDelete at the DB level (see the TASK-006 migrations), so
        // no manual cleanup is needed here.
    }

    /**
     * Set the user's active project (FR-012). Ownership is enforced via
     * getOwned() — a user can only activate their own project.
     */
    public function setActive(User $user, int $projectId): Project
    {
        $project = $this->getOwned($user, $projectId);

        $user->active_project_id = $project->id;
        $user->save();

        return $project;
    }

    public function clearActive(User $user): void
    {
        $user->active_project_id = null;
        $user->save();
    }

    /**
     * Security note (TASK-007 audit): deliberately re-verifies ownership
     * here rather than trusting active_project_id blindly. The only
     * current write path (setActive() above) already enforces ownership
     * before setting it, so this wasn't exploitable through the app's own
     * UI — but this method's result flows into AI prompt context
     * (ProjectContextManager) and Telegram replies, so it re-checks
     * user_id explicitly as defense-in-depth rather than relying on a
     * single write-side guarantee holding forever.
     */
    public function getActive(User $user): ?Project
    {
        if ($user->active_project_id === null) {
            return null;
        }

        return Project::where('id', $user->active_project_id)
            ->where('user_id', $user->id)
            ->first();
    }
}
