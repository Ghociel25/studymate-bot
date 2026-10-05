<?php

namespace Tests\Feature;

use App\Exceptions\ConversationAccessDeniedException;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversation\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent in this environment — same sandbox
 * constraint as TASK-001/002/003 (Packagist blocked, vendor/ never
 * installed here). Written to be run with
 * `php artisan test --filter=ConversationServiceTest`.
 */
class ConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConversationService
    {
        return $this->app->make(ConversationService::class);
    }

    public function test_user_can_start_a_conversation(): void
    {
        $user = User::factory()->create();

        $conversation = $this->service()->startConversation($user, 'Tugas OOP');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'user_id' => $user->id,
            'title' => 'Tugas OOP',
        ]);
    }

    public function test_user_can_add_and_retrieve_messages_in_order(): void
    {
        $user = User::factory()->create();
        $conversation = $this->service()->startConversation($user);

        $this->service()->addMessage($conversation, 'user', 'halo');
        $this->service()->addMessage($conversation, 'assistant', 'hai, ada yang bisa dibantu?');

        $messages = $this->service()->getMessages($conversation);

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('assistant', $messages[1]->role);
    }

    public function test_adding_message_with_invalid_role_is_rejected(): void
    {
        $user = User::factory()->create();
        $conversation = $this->service()->startConversation($user);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->addMessage($conversation, 'system', 'should not be allowed here');
    }

    public function test_owner_can_fetch_their_own_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $fetched = $this->service()->getOwned($user, $conversation->id);

        $this->assertSame($conversation->id, $fetched->id);
    }

    public function test_user_cannot_access_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $owner->id]);

        $this->expectException(ConversationAccessDeniedException::class);

        $this->service()->getOwned($intruder, $conversation->id);
    }

    public function test_fetching_nonexistent_conversation_throws_same_exception_as_unauthorized(): void
    {
        $user = User::factory()->create();

        $this->expectException(ConversationAccessDeniedException::class);

        $this->service()->getOwned($user, 999999);
    }

    public function test_user_can_continue_their_latest_conversation(): void
    {
        $user = User::factory()->create();
        $older = Conversation::factory()->create(['user_id' => $user->id, 'updated_at' => now()->subHour()]);
        $newer = Conversation::factory()->create(['user_id' => $user->id, 'updated_at' => now()]);

        $latest = $this->service()->latestForUser($user);

        $this->assertSame($newer->id, $latest->id);
        $this->assertNotSame($older->id, $latest->id);
    }

    public function test_user_with_no_conversations_gets_null_for_latest(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->service()->latestForUser($user));
    }
}
