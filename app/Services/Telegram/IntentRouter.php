<?php

namespace App\Services\Telegram;

use App\DTO\RoutedIntent;
use App\Support\Mode;

/**
 * Determines which Mode a message should be processed under (FR-004),
 * per SYSTEM_DESIGN.md's "Intent Router" pipeline step.
 *
 * Mode is decided per-message, not as sticky session state: there is no
 * "active mode" column anywhere in DATABASE_DESIGN.md, so this app
 * doesn't invent one. A message either explicitly names its mode
 * ("/coding ...") or defaults to General — exactly what AI_DESIGN.md's
 * pipeline diagram implies (Intent -> Mode happens per request).
 */
class IntentRouter
{
    public function route(string $text): RoutedIntent
    {
        $text = trim($text);
        $firstToken = strtolower(explode(' ', explode("\n", $text)[0] ?? '')[0] ?? '');

        $mode = Mode::fromCommand($firstToken);

        if ($mode === null) {
            return new RoutedIntent(Mode::General, $text);
        }

        // Strip the leading "/coding" (etc.) token, keep whatever follows
        // as the actual input. "/coding" alone (no extra text) leaves an
        // empty input — the caller decides how to handle that.
        $remainder = trim(substr($text, strlen($firstToken)));

        return new RoutedIntent($mode, $remainder);
    }
}
