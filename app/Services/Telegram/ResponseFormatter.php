<?php

namespace App\Services\Telegram;

/**
 * Converts raw AI text into Telegram HTML parse_mode-safe output.
 *
 * TelegramApiClient sends every message with parse_mode: HTML (TASK-002).
 * CommandHandler's static /start text is hand-written valid HTML, but AI
 * output is arbitrary and may contain literal <, >, & characters that
 * would otherwise produce malformed HTML and make Telegram reject the
 * request. This also fulfils STYLE_GUIDE.md's "Gunakan code block untuk
 * code" by converting markdown-style fences to Telegram's <pre>/<code>.
 *
 * Deliberately basic: handles the common ```code``` and `code` markdown
 * patterns, not a full Markdown parser. Good enough for mock/early
 * responses; can be extended if a real provider's output needs more.
 */
class ResponseFormatter
{
    public function format(string $content): string
    {
        // Control-byte markers: htmlspecialchars() never touches these,
        // so they pass through the escaping pass below unchanged and can
        // be swapped back for real HTML afterward.
        $placeholders = [];

        $stash = function (string $html) use (&$placeholders): string {
            $key = "\x01".count($placeholders)."\x02";
            $placeholders[$key] = $html;

            return $key;
        };

        // Fenced code blocks first, so their contents aren't touched by
        // the inline-code pass below.
        $content = preg_replace_callback(
            '/```(?:\w+\n)?(.*?)```/s',
            fn (array $m) => $stash('<pre><code>'.e(trim($m[1])).'</code></pre>'),
            $content
        );

        // Inline `code` spans.
        $content = preg_replace_callback(
            '/`([^`\n]+)`/',
            fn (array $m) => $stash('<code>'.e($m[1]).'</code>'),
            $content
        );

        // Escape everything that's left (plain prose) so stray <, >, &
        // can't break Telegram's HTML parser.
        $content = e($content);

        // Swap placeholders back for their real (already-escaped-inside) HTML.
        $content = strtr($content, $placeholders);

        return trim($content);
    }
}
