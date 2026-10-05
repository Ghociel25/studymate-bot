<?php

namespace App\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Contracts\ContextManagerInterface;
use App\Services\AI\Providers\MockAIProvider;
use App\Services\AI\ProjectContextManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // AI_DESIGN.md "Provider Rule": business logic depends on
        // AIProviderInterface only. `mock` is the only implementation so
        // far — a real provider (once chosen, see PRD "Open Decisions")
        // gets its own class here behind the same interface, selected via
        // the AI_PROVIDER env var. No other code needs to change.
        $this->app->bind(AIProviderInterface::class, function () {
            return match (config('services.ai.provider', 'mock')) {
                'mock' => new MockAIProvider,
                default => new MockAIProvider,
            };
        });

        // Fulfils the TODO left in TASK-003's NullContextManager docblock:
        // `conversations` (TASK-004) and `projects` (TASK-006) both exist
        // now. AIService itself needed zero changes for this swap.
        $this->app->bind(ContextManagerInterface::class, ProjectContextManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
