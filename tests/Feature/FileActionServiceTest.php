<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use App\Services\File\FileActionService;
use App\Services\File\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=FileActionServiceTest`.
 */
class FileActionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_running_action_on_processed_file_returns_ai_response_with_file_content_in_context(): void
    {
        $user = User::factory()->create();
        $fileService = $this->app->make(FileService::class);
        $file = $fileService->store($user, 'tugas.txt', 'text/plain', 'Soal: hitung luas lingkaran jari-jari 7.');

        $response = $this->app->make(FileActionService::class)->run($user, $file, 'ringkas');

        $this->assertTrue($response->success);
        $this->assertNotNull($response->content);
    }

    public function test_action_on_unprocessed_file_throws(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->create(['user_id' => $user->id, 'processing_status' => 'failed']);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(FileActionService::class)->run($user, $file, 'ringkas');
    }

    public function test_unknown_action_throws(): void
    {
        $user = User::factory()->create();
        $fileService = $this->app->make(FileService::class);
        $file = $fileService->store($user, 'tugas.txt', 'text/plain', 'isi file');

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(FileActionService::class)->run($user, $file, 'tidakada');
    }
}
