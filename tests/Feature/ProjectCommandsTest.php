<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises /newproject, /projects, /project through the real webhook
 * endpoint, same Http::fake() pattern as TelegramWebhookTest (TASK-002)
 * and ModeRoutingTest (TASK-005). New file — neither of those was
 * modified.
 *
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=ProjectCommandsTest`.
 */
class ProjectCommandsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.webhook_secret' => self::SECRET]);
        config(['services.telegram.bot_token' => 'test-bot-token']);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
    }

    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ]);
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

    public function test_newproject_creates_and_activates_a_project(): void
    {
        $this->postWebhook($this->textUpdate('/newproject Tugas OOP', 101, 101));

        $this->assertDatabaseHas('projects', ['name' => 'Tugas OOP']);

        $user = User::where('telegram_id', 101)->first();
        $this->assertNotNull($user->active_project_id);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'Tugas OOP'));
    }

    public function test_projects_lists_user_projects_with_active_marker(): void
    {
        $this->postWebhook($this->textUpdate('/newproject Skripsi', 102, 102));
        $this->postWebhook($this->textUpdate('/projects', 102, 102));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'Skripsi'));
    }

    public function test_project_with_no_args_shows_active_project(): void
    {
        $this->postWebhook($this->textUpdate('/newproject KKP', 103, 103));
        $this->postWebhook($this->textUpdate('/project', 103, 103));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'KKP'));
    }

    public function test_new_conversation_links_to_active_project(): void
    {
        $this->postWebhook($this->textUpdate('/newproject Laravel Bot', 104, 104));
        $this->postWebhook($this->textUpdate('halo, lagi bahas apa ya', 104, 104));

        $user = User::where('telegram_id', 104)->first();
        $conversation = $user->conversations()->first();

        $this->assertNotNull($conversation);
        $this->assertSame($user->active_project_id, $conversation->project_id);
    }

    public function test_cannot_switch_to_another_users_project_id(): void
    {
        $this->postWebhook($this->textUpdate('/newproject Private Project', 201, 201));
        $owner = User::where('telegram_id', 201)->first();
        $projectId = $owner->active_project_id;

        $this->postWebhook($this->textUpdate("/project {$projectId}", 202, 202));

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'bukan punya kamu'));

        $intruder = User::where('telegram_id', 202)->first();
        $this->assertNull($intruder->active_project_id);
    }

    public function test_start_and_unknown_command_still_work_after_project_commands_added(): void
    {
        $this->postWebhook($this->textUpdate('/start', 301, 301));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'StudyMate Bot'));

        $this->postWebhook($this->textUpdate('/unknowncommand', 302, 302));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'belum tersedia'));
    }
}
