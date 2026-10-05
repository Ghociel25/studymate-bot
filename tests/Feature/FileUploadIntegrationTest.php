<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises the full document-upload pipeline through the real webhook:
 * Telegram document update -> getFile -> download -> validate -> private
 * storage -> extract -> status -> reply; then /file <id> <action>.
 *
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=FileUploadIntegrationTest`.
 */
class FileUploadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        config(['services.telegram.webhook_secret' => self::SECRET]);
        config(['services.telegram.bot_token' => 'test-bot-token']);
    }

    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ]);
    }

    private function documentUpdate(string $fileName, int $fileSize, int $telegramUserId, int $chatId): array
    {
        return [
            'update_id' => random_int(1, 999999),
            'message' => [
                'message_id' => 1,
                'date' => now()->timestamp,
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => ['id' => $telegramUserId, 'is_bot' => false, 'first_name' => 'Budi'],
                'document' => [
                    'file_id' => 'FAKE_FILE_ID_123',
                    'file_name' => $fileName,
                    'mime_type' => 'text/plain',
                    'file_size' => $fileSize,
                ],
            ],
        ];
    }

    private function textUpdate(string $text, int $telegramUserId, int $chatId): array
    {
        return [
            'update_id' => random_int(1, 999999),
            'message' => [
                'message_id' => 1,
                'date' => now()->timestamp,
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => ['id' => $telegramUserId, 'is_bot' => false, 'first_name' => 'Budi'],
                'text' => $text,
            ],
        ];
    }

    public function test_uploading_a_txt_file_stores_it_privately_and_processes_it(): void
    {
        Http::fake([
            '*/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => 'documents/file_1.txt']], 200),
            '*/file/bot*' => Http::response('Isi tugas: jelaskan algoritma bubble sort.', 200),
            '*/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $this->postWebhook($this->documentUpdate('tugas.txt', 500, 401, 401));

        $this->assertDatabaseHas('files', [
            'original_name' => 'tugas.txt',
            'processing_status' => 'processed',
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'diproses'));
    }

    public function test_oversized_file_is_rejected_before_download(): void
    {
        Http::fake(['*/sendMessage' => Http::response(['ok' => true], 200)]);

        $maxBytes = config('file_processing.max_size_kb') * 1024;
        $this->postWebhook($this->documentUpdate('besar.txt', $maxBytes + 1, 402, 402));

        $this->assertDatabaseCount('files', 0);
        // getFile should never even be called for a rejected upload.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'getFile'));
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        Http::fake(['*/sendMessage' => Http::response(['ok' => true], 200)]);

        $this->postWebhook($this->documentUpdate('virus.exe', 500, 403, 403));

        $this->assertDatabaseCount('files', 0);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'not supported'));
    }

    public function test_file_action_command_works_after_upload(): void
    {
        Http::fake([
            '*/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => 'documents/file_2.txt']], 200),
            '*/file/bot*' => Http::response('Soal UTS: buktikan teorema Pythagoras.', 200),
            '*/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $this->postWebhook($this->documentUpdate('soal.txt', 500, 404, 404));
        $file = File::where('original_name', 'soal.txt')->firstOrFail();

        $this->postWebhook($this->textUpdate("/file {$file->id} ringkas", 404, 404));

        $this->assertDatabaseHas('ai_requests', ['mode' => 'general', 'status' => 'success']);
    }

    public function test_file_action_on_another_users_file_is_denied(): void
    {
        Http::fake([
            '*/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => 'documents/file_3.txt']], 200),
            '*/file/bot*' => Http::response('rahasia', 200),
            '*/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $this->postWebhook($this->documentUpdate('rahasia.txt', 500, 501, 501));
        $file = File::where('original_name', 'rahasia.txt')->firstOrFail();

        $this->postWebhook($this->textUpdate("/file {$file->id} ringkas", 502, 502));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'bukan punya kamu'));
    }

    /**
     * Final regression proof: every prior task's core behavior still
     * works after all seven tasks are combined in one codebase.
     */
    public function test_full_regression_start_chat_project_and_file_all_still_work(): void
    {
        Http::fake([
            '*/getFile*' => Http::response(['ok' => true, 'result' => ['file_path' => 'documents/file_4.txt']], 200),
            '*/file/bot*' => Http::response('Konten file regresi.', 200),
            '*/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $uid = 601;

        $this->postWebhook($this->textUpdate('/start', $uid, $uid)); // TASK-002
        $this->postWebhook($this->textUpdate('halo, apa kabar', $uid, $uid)); // TASK-005
        $this->postWebhook($this->textUpdate('/newproject Regresi Test', $uid, $uid)); // TASK-006
        $this->postWebhook($this->documentUpdate('regresi.txt', 500, $uid, $uid)); // TASK-007

        $user = User::where('telegram_id', $uid)->first();

        $this->assertNotNull($user);
        $this->assertDatabaseHas('conversations', ['user_id' => $user->id]);
        $this->assertDatabaseHas('projects', ['user_id' => $user->id, 'name' => 'Regresi Test']);
        $this->assertDatabaseHas('files', ['user_id' => $user->id, 'original_name' => 'regresi.txt']);
    }
}
