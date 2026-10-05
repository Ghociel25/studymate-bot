<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Return a minimal JSON health status for uptime/monitoring checks.
     *
     * Kept intentionally thin: no business logic, no external calls.
     * Database/queue/AI provider health can be added here later if needed,
     * but that must stay a deliberate, documented decision (see SECURITY.md
     * on not leaking internal details in responses).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'env' => config('app.env'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
