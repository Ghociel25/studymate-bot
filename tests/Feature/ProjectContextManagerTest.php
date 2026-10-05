<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Services\AI\ProjectContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=ProjectContextManagerTest`.
 */
class ProjectContextManagerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): ProjectContextManager
    {
        return new ProjectContextManager;
    }

    public function test_returns_empty_context_when_no_project_and_no_conversation(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $this->manager()->build($user));
    }

    public function test_uses_active_project_when_no_conversation_given(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Skripsi']);
        $user->update(['active_project_id' => $project->id]);

        $context = $this->manager()->build($user->fresh());

        $this->assertCount(1, $context);
        $this->assertStringContainsString('Skripsi', $context[0]['content']);
    }

    public function test_uses_conversations_linked_project_over_active_project(): void
    {
        $user = User::factory()->create();
        $activeProject = Project::factory()->create(['user_id' => $user->id, 'name' => 'Active One']);
        $linkedProject = Project::factory()->create(['user_id' => $user->id, 'name' => 'Linked One']);
        $user->update(['active_project_id' => $activeProject->id]);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'project_id' => $linkedProject->id,
        ]);

        $context = $this->manager()->build($user->fresh(), $conversation->id);

        $this->assertStringContainsString('Linked One', $context[0]['content']);
        $this->assertStringNotContainsString('Active One', $context[0]['content']);
    }

    public function test_conversation_belonging_to_another_user_yields_no_context(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $owner->id,
            'project_id' => $project->id,
        ]);

        $context = $this->manager()->build($intruder, $conversation->id);

        $this->assertSame([], $context);
    }
}
