<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers TESTING.md's MVP priority list items: webhook validation, user
 * creation, and "/start". Uses Http::fake() so no real request ever goes
 * to Telegram's API (TESTING.md: "Jangan bergantung pada API live").
 *
 * NOTE: not executed by the AI agent in this environment — see the
 * TASK-002 verification note for why (Packagist access is blocked in the
 * sandbox, so vendor/ was never installed here). Run with
 * `php artisan test` locally/CI before marking this task's tests as passing.
 */
class TelegramWebhookTest extends TestCase
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

    private function postWebhook(array $payload, ?string $secret = self::SECRET): \Illuminate\Testing\TestResponse
    {
        $headers = $secret !== null
            ? ['X-Telegram-Bot-Api-Secret-Token' => $secret]
            : [];

        return $this->postJson('/telegram/webhook', $payload, $headers);
    }

    private function startUpdate(int $telegramUserId = 111222333, int $chatId = 111222333): array
    {
        return [
            'update_id' => 1001,
            'message' => [
                'message_id' => 1,
                'date' => now()->timestamp,
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => [
                    'id' => $telegramUserId,
                    'is_bot' => false,
                    'first_name' => 'Budi',
                    'username' => 'budi_dev',
                ],
                'text' => '/start',
            ],
        ];
    }

    public function test_webhook_rejects_request_without_valid_secret(): void
    {
        $response = $this->postWebhook($this->startUpdate(), secret: 'wrong-secret');

        $response->assertStatus(403);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_webhook_rejects_request_with_missing_secret_header(): void
    {
        $response = $this->postWebhook($this->startUpdate(), secret: null);

        $response->assertStatus(403);
    }

    public function test_start_command_creates_user_and_replies(): void
    {
        $response = $this->postWebhook($this->startUpdate(telegramUserId: 555, chatId: 555));

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('users', [
            'telegram_id' => 555,
            'username' => 'budi_dev',
            'first_name' => 'Budi',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['chat_id'] === 555
                && str_contains($request['text'], 'StudyMate Bot');
        });
    }

    public function test_start_command_recognizes_existing_user_without_duplicating(): void
    {
        User::factory()->create(['telegram_id' => 777]);

        $this->postWebhook($this->startUpdate(telegramUserId: 777, chatId: 777));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_unsupported_update_does_not_crash_and_still_returns_200(): void
    {
        $response = $this->postWebhook([
            'update_id' => 999,
            'edited_message' => ['message_id' => 1],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_command_gets_controlled_fallback_reply(): void
    {
        $update = $this->startUpdate(telegramUserId: 888, chatId: 888);
        $update['message']['text'] = '/unknowncommand';

        $this->postWebhook($update);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && str_contains($request['text'], 'belum tersedia');
        });
    }
}
