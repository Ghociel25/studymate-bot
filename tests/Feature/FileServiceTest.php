<?php

namespace Tests\Feature;

use App\Exceptions\FileAccessDeniedException;
use App\Models\File;
use App\Models\User;
use App\Services\File\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=FileServiceTest`.
 */
class FileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function service(): FileService
    {
        return $this->app->make(FileService::class);
    }

    public function test_txt_file_within_size_limit_is_accepted(): void
    {
        $extension = $this->service()->validate('notes.txt', 1000);

        $this->assertSame('txt', $extension);
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->validate('virus.exe', 1000);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $maxBytes = config('file_processing.max_size_kb') * 1024;

        $this->expectException(InvalidArgumentException::class);

        $this->service()->validate('big.txt', $maxBytes + 1);
    }

    public function test_txt_file_is_stored_privately_and_marked_processed(): void
    {
        $user = User::factory()->create();

        $file = $this->service()->store($user, 'notes.txt', 'text/plain', "Ini isi catatan saya.\nBaris kedua.");

        $this->assertSame('processed', $file->processing_status);
        $this->assertDatabaseHas('files', ['id' => $file->id, 'user_id' => $user->id, 'original_name' => 'notes.txt']);
        Storage::disk('local')->assertExists($file->storage_path);
    }

    public function test_extracted_text_is_retrievable_for_processed_txt(): void
    {
        $user = User::factory()->create();

        $file = $this->service()->store($user, 'notes.txt', 'text/plain', 'Halo dunia');

        $this->assertSame('Halo dunia', $this->service()->getExtractedText($file));
    }

    public function test_pdf_upload_succeeds_but_is_marked_failed_extraction_honestly(): void
    {
        $user = User::factory()->create();

        $file = $this->service()->store($user, 'tugas.pdf', 'application/pdf', '%PDF-1.4 fake pdf bytes');

        // Upload/storage itself succeeds (allowlisted type)...
        $this->assertDatabaseHas('files', ['id' => $file->id, 'original_name' => 'tugas.pdf']);
        Storage::disk('local')->assertExists($file->storage_path);

        // ...but extraction honestly reports it isn't implemented, rather
        // than fabricating extracted content.
        $this->assertSame('failed', $file->processing_status);
        $this->assertNull($this->service()->getExtractedText($file));
    }

    public function test_owner_can_fetch_their_own_file(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->create(['user_id' => $user->id]);

        $this->assertSame($file->id, $this->service()->getOwned($user, $file->id)->id);
    }

    public function test_user_cannot_access_another_users_file(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $file = File::factory()->create(['user_id' => $owner->id]);

        $this->expectException(FileAccessDeniedException::class);

        $this->service()->getOwned($intruder, $file->id);
    }

    public function test_uploaded_content_is_never_executed_only_stored(): void
    {
        $user = User::factory()->create();

        // A .txt file containing shell-like content: must be stored as
        // inert bytes, never interpreted/executed.
        $file = $this->service()->store($user, 'suspicious.txt', 'text/plain', "<?php echo 'should never run'; ?>");

        $this->assertSame('processed', $file->processing_status);
        $this->assertStringContainsString(
            "should never run",
            $this->service()->getExtractedText($file)
        );
        // The assertion above only proves the raw text was stored/read
        // back as a string — FileService has no code path that ever
        // calls eval/include/require on file content.
    }
}
