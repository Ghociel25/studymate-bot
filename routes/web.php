<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// This is a Telegram-first backend with no web frontend in MVP (see PRD.md
// "Out of Scope MVP"). Root path returns a minimal identifying response
// instead of the default Laravel marketing page.
Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'status' => 'running',
    ]);
});

// Application health check for uptime monitoring / deployment verification.
// See DEPLOYMENT.md checklist item "logs/monitoring".
Route::get('/health', [HealthController::class, 'index']);

// Telegram webhook. Secret token is verified by middleware before the
// controller runs; CSRF is excluded for this route in bootstrap/app.php
// since Telegram cannot send a Laravel CSRF token.
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.webhook.secret')
    ->name('telegram.webhook');
