<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\File\FileService;
use App\Services\Project\ProjectService;
use App\Services\Telegram\TelegramApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests added specifically for the TASK-007 audit/hardening pass — cases
 * the audit asked for that weren't already covered by earlier tasks'
 * test files (which are untouched).
 *
 * NOTE: not executed by the agent — same sandbox constraint as every
 * previous task. Run with `php artisan test --filter=SecurityAuditTest`.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_get_active_project_never_returns_another_users_project_even_if_active_project_id_is_corrupted(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $ownersProject = Project::factory()->create(['user_id' => $owner->id]);

        // Simulate active_project_id pointing at someone else's project —
        // the normal setActive() flow can't produce this, but this proves
        // getActive() doesn't blindly trust the column if it ever did.
        $intruder->forceFill(['active_project_id' => $ownersProject->id])->save();

        $result = $this->app->make(ProjectService::class)->getActive($intruder->fresh());

        $this->assertNull($result);
    }

    public function test_active_project_relation_also_refuses_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $ownersProject = Project::factory()->create(['user_id' => $owner->id]);

        $intruder->forceFill(['active_project_id' => $ownersProject->id])->save();

        $this->assertNull($intruder->fresh()->activeProject);
    }

    public function test_active_project_id_is_cast_to_integer(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $user->update(['active_project_id' => $project->id]);

        $fresh = $user->fresh();

        $this->assertIsInt($fresh->active_project_id);
        $this->assertTrue($fresh->active_project_id === $project->id); // strict comparison, same as CommandHandler::listProjects()
    }

    public function test_mime_extension_mismatch_is_logged_but_does_not_block_upload(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $file = $this->app->make(FileService::class)->store(
            $user,
            'notes.txt',
            'application/pdf', // mismatched on purpose
            'isi catatan'
        );

        $this->assertSame('processed', $file->processing_status);
        Log::shouldHaveReceived('warning')
            ->with('file_service.mime_extension_mismatch', \Mockery::type('array'))
            ->once();
    }

    public function test_malicious_filename_cannot_escape_the_storage_directory(): void
    {
        $user = User::factory()->create();

        $file = $this->app->make(FileService::class)->store(
            $user,
            '../../../../etc/passwd.txt', // path-traversal attempt in the filename
            'text/plain',
            'irrelevant content'
        );

        // storage_path must stay inside this user's own telegram-files
        // directory — the malicious segments must not appear in it.
        $this->assertStringStartsWith("telegram-files/{$user->id}/", $file->storage_path);
        $this->assertStringNotContainsString('..', $file->storage_path);
        Storage::disk('local')->assertExists($file->storage_path);
    }

    public function test_telegram_connection_failure_does_not_leak_bot_token_in_logs(): void
    {
        Log::spy();

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection failure');
        });

        $client = new TelegramApiClient('SUPER-SECRET-TOKEN-12345');
        $result = $client->sendMessage(123, 'test');

        $this->assertFalse($result);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context = []) {
                $haystack = $message.json_encode($context);

                return ! str_contains($haystack, 'SUPER-SECRET-TOKEN-12345');
            });
    }
}
