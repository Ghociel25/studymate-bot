<?php

namespace Tests\Feature;

use App\DTO\AIRequestData;
use App\DTO\AIResponseData;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\AI\Contracts\AIProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent in this environment (same Packagist/
 * sandbox constraint as TASK-001/002 — see those tasks' verification
 * notes). Written to be run with `php artisan test --filter=AIServiceTest`.
 */
class AIServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_respond_returns_content_from_the_bound_provider(): void
    {
        $user = User::factory()->create();

        /** @var AIService $service */
        $service = $this->app->make(AIService::class);

        $response = $service->respond($user, 'general', 'halo, apa kabar?');

        $this->assertTrue($response->success);
        $this->assertNotNull($response->content);
        $this->assertSame('mock', $response->provider);
    }

    public function test_respond_persists_usage_metadata_to_ai_requests(): void
    {
        $user = User::factory()->create();

        /** @var AIService $service */
        $service = $this->app->make(AIService::class);
        $service->respond($user, 'coding', 'tolong jelaskan array di php');

        $this->assertDatabaseHas('ai_requests', [
            'user_id' => $user->id,
            'mode' => 'coding',
            'provider' => 'mock',
            'status' => 'success',
        ]);
    }

    public function test_respond_handles_provider_exception_without_throwing(): void
    {
        $user = User::factory()->create();

        // Swap the bound provider for one that throws, proving AIService
        // doesn't care which concrete provider it's given (the acceptance
        // criterion for this task) and that failures are handled safely.
        $this->app->bind(AIProviderInterface::class, function () {
            return new class implements AIProviderInterface
            {
                public function complete(AIRequestData $request): AIResponseData
                {
                    throw new \RuntimeException('simulated provider outage');
                }
            };
        });

        /** @var AIService $service */
        $service = $this->app->make(AIService::class);
        $response = $service->respond($user, 'general', 'test');

        $this->assertFalse($response->success);
        $this->assertSame('provider_exception', $response->errorCode);

        $this->assertDatabaseHas('ai_requests', [
            'user_id' => $user->id,
            'status' => 'error',
            'error_code' => 'provider_exception',
        ]);
    }

    public function test_provider_is_swappable_without_changing_ai_service(): void
    {
        $user = User::factory()->create();

        $this->app->bind(AIProviderInterface::class, function () {
            return new class implements AIProviderInterface
            {
                public function complete(AIRequestData $request): AIResponseData
                {
                    return AIResponseData::ok(
                        content: 'custom-fake-response',
                        provider: 'custom-fake',
                        model: 'fake-1',
                    );
                }
            };
        });

        /** @var AIService $service */
        $service = $this->app->make(AIService::class);
        $response = $service->respond($user, 'general', 'test');

        $this->assertSame('custom-fake', $response->provider);
        $this->assertSame('custom-fake-response', $response->content);
    }
}
