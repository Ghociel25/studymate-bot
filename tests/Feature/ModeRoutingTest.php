<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises mode routing through the real webhook endpoint (not just
 * CommandHandler in isolation), using the same Http::fake() pattern as
 * TelegramWebhookTest (TASK-002). A new file, not a modification of
 * TelegramWebhookTest.php — that file's own tests still cover /start and
 * the unknown-command fallback and were left untouched.
 *
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=ModeRoutingTest`.
 */
class ModeRoutingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.webhook_secret' => self::SECRET]);
        config(['services.telegram.bot_token' => 'test-bot-token']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ]);
    }

    private function textUpdate(string $text, int $telegramUserId = 42, int $chatId = 42): array
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

    public function test_plain_text_message_gets_routed_through_general_mode_and_replied(): void
    {
        $this->postWebhook($this->textUpdate('halo bot'));

        $this->assertDatabaseHas('ai_requests', ['mode' => 'general', 'status' => 'success']);
        $this->assertDatabaseHas('messages', ['role' => 'user', 'content' => 'halo bot']);
        $this->assertDatabaseCount('messages', 2); // user + assistant

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage'));
    }

    public function test_coding_command_is_routed_to_coding_mode(): void
    {
        $this->postWebhook($this->textUpdate('/coding jelaskan array di php'));

        $this->assertDatabaseHas('ai_requests', ['mode' => 'coding', 'status' => 'success']);
        $this->assertDatabaseHas('messages', ['role' => 'user', 'content' => 'jelaskan array di php']);
    }

    public function test_conversation_is_reused_across_messages_same_user(): void
    {
        $this->postWebhook($this->textUpdate('pesan pertama', telegramUserId: 99, chatId: 99));
        $this->postWebhook($this->textUpdate('pesan kedua', telegramUserId: 99, chatId: 99));

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 4); // 2 user + 2 assistant, same conversation
    }

    public function test_start_command_still_works_unchanged_after_mode_routing_added(): void
    {
        $this->postWebhook($this->textUpdate('/start', telegramUserId: 7, chatId: 7));

        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'sendMessage')
                && str_contains($r['text'], 'StudyMate Bot');
        });

        // /start must not create a conversation/message or hit the AI
        // pipeline — its TASK-002 behavior is unchanged.
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('ai_requests', 0);
    }

    public function test_unrecognized_slash_command_still_gets_the_task_002_fallback(): void
    {
        $this->postWebhook($this->textUpdate('/unknowncommand', telegramUserId: 8, chatId: 8));

        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'sendMessage')
                && str_contains($r['text'], 'belum tersedia');
        });

        $this->assertDatabaseCount('ai_requests', 0);
    }
}
