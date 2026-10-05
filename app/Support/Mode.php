<?php

namespace App\Support;

/**
 * The four AI modes defined in AI_DESIGN.md. Backed by the exact string
 * values already used elsewhere (`ai_requests.mode`, AIService's `$mode`
 * string parameter) so converting to/from storage is just `->value`.
 */
enum Mode: string
{
    case General = 'general';
    case Coding = 'coding';
    case Assignment = 'assignment';
    case Study = 'study';

    /**
     * Maps a recognized slash command to its Mode. Returns null for
     * anything else (plain text, or a command this app doesn't know).
     */
    public static function fromCommand(string $command): ?self
    {
        return match ($command) {
            '/chat' => self::General,
            '/coding' => self::Coding,
            '/tugas', '/assignment' => self::Assignment,
            '/belajar', '/study' => self::Study,
            default => null,
        };
    }
}
