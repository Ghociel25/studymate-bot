<?php

namespace App\Services\AI;

use App\Support\Mode;

/**
 * Additional system instructions per mode, layered on top of
 * PromptBuilder's base system rules (never replacing them — AI_DESIGN.md:
 * "System rules lebih tinggi daripada user instruction", and these are
 * themselves still system-level rules, just mode-specific ones).
 *
 * Content follows each mode's SRS requirement directly:
 * - FR-005 Coding: generate/explain/debug/refactor/review; never claim
 *   code was executed.
 * - FR-006 Assignment: understand the problem, identify requirements,
 *   propose an approach, draft a solution, check answers.
 * - FR-007 Study: step-by-step explanation, examples, exercises,
 *   discussion.
 * General has no addendum — base rules are sufficient.
 */
class ModePromptRules
{
    /**
     * @return array<int, array{role: string, content: string}>
     */
    public static function forMode(Mode $mode): array
    {
        $content = match ($mode) {
            Mode::General => null,
            Mode::Coding => <<<'TEXT'
                Mode: Coding. Bantu generate, explain, debug, refactor, atau
                review kode sesuai permintaan user. Jangan pernah mengklaim
                kode telah dijalankan/diuji kecuali itu benar-benar terjadi.
                Jelaskan alasan di balik solusi, bukan cuma kode mentah.
                TEXT,
            Mode::Assignment => <<<'TEXT'
                Mode: Assignment. Bantu memahami soal, mengidentifikasi
                requirement, menyusun pendekatan, membuat draft solusi, dan
                memeriksa jawaban. Dorong user memahami alasannya, bukan
                cuma memberi jawaban akhir mentah-mentah.
                TEXT,
            Mode::Study => <<<'TEXT'
                Mode: Study. Berikan penjelasan bertahap, contoh konkret,
                latihan, dan pembahasan. Sesuaikan kedalaman penjelasan
                dengan pertanyaan user.
                TEXT,
        };

        if ($content === null) {
            return [];
        }

        return [['role' => 'system', 'content' => $content]];
    }
}
