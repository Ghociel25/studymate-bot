<?php

namespace App\Services\AI;

use InvalidArgumentException;

/**
 * Maps a file action keyword (from TELEGRAM_DESIGN.md's File Actions menu:
 * 📖 Jelaskan, 📝 Ringkas, 🔍 Analisis, ✏️ Bantu Kerjakan, 📋 Buat Outline)
 * to the instruction given to the AI, per FR-009.
 */
class FileActionInstructions
{
    public const ACTIONS = ['jelaskan', 'ringkas', 'analisis', 'bantukerjakan', 'outline'];

    public static function forAction(string $action): string
    {
        return match ($action) {
            'jelaskan' => 'Jelaskan isi file ini dengan jelas dan mudah dipahami.',
            'ringkas' => 'Ringkas isi file ini, ambil poin-poin pentingnya saja.',
            'analisis' => 'Analisis isi file ini: struktur, poin kunci, dan hal yang perlu diperhatikan.',
            'bantukerjakan' => 'Bantu user memahami dan mengerjakan apa yang diminta di file ini. Jelaskan pendekatannya, jangan cuma kasih jawaban akhir mentah.',
            'outline' => 'Buatkan outline/kerangka dari isi file ini.',
            default => throw new InvalidArgumentException(
                "Unknown file action '{$action}'. Expected one of: ".implode(', ', self::ACTIONS)
            ),
        };
    }
}
