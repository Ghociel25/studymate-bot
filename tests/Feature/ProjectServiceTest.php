<?php

namespace Tests\Feature;

use App\Exceptions\ProjectAccessDeniedException;
use App\Models\Project;
use App\Models\User;
use App\Services\Project\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=ProjectServiceTest`.
 */
class ProjectServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProjectService
    {
        return $this->app->make(ProjectService::class);
    }

    public function test_user_can_create_a_project(): void
    {
        $user = User::factory()->create();

        $project = $this->service()->create($user, 'Skripsi', 'Analisis performa sistem');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'user_id' => $user->id,
            'name' => 'Skripsi',
            'status' => 'active',
        ]);
    }

    public function test_owner_can_fetch_their_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $fetched = $this->service()->getOwned($user, $project->id);

        $this->assertSame($project->id, $fetched->id);
    }

    public function test_user_cannot_access_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->expectException(ProjectAccessDeniedException::class);

        $this->service()->getOwned($intruder, $project->id);
    }

    public function test_user_can_update_their_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $updated = $this->service()->update($user, $project->id, ['status' => 'completed']);

        $this->assertSame('completed', $updated->status);
    }

    public function test_updating_another_users_project_is_denied(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->expectException(ProjectAccessDeniedException::class);

        $this->service()->update($intruder, $project->id, ['status' => 'completed']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->update($user, $project->id, ['status' => 'not-a-real-status']);
    }

    public function test_user_can_delete_their_own_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->service()->delete($user, $project->id);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_setting_active_project_updates_user_and_enforces_ownership(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->service()->setActive($user, $project->id);

        $this->assertSame($project->id, $user->fresh()->active_project_id);
        $this->assertSame($project->id, $this->service()->getActive($user)->id);
    }

    public function test_cannot_activate_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->expectException(ProjectAccessDeniedException::class);

        $this->service()->setActive($intruder, $project->id);
    }

    public function test_deleting_active_project_clears_users_active_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $this->service()->setActive($user, $project->id);

        $this->service()->delete($user, $project->id);

        $this->assertNull($user->fresh()->active_project_id);
    }

    public function test_user_with_no_active_project_gets_null(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service()->getActive($user));
    }
}
