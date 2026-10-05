<?php

namespace Tests\Unit;

use App\Services\Telegram\ResponseFormatter;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=ResponseFormatterTest`.
 */
class ResponseFormatterTest extends TestCase
{
    private function formatter(): ResponseFormatter
    {
        return new ResponseFormatter;
    }

    public function test_plain_text_passes_through(): void
    {
        $this->assertSame('Halo, ada yang bisa dibantu?', $this->formatter()->format('Halo, ada yang bisa dibantu?'));
    }

    public function test_special_html_characters_are_escaped(): void
    {
        $result = $this->formatter()->format('if (a < b && b > c) { ... }');

        $this->assertStringNotContainsString('<', str_replace(['&lt;', '&gt;'], '', $result));
        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringContainsString('&gt;', $result);
        $this->assertStringContainsString('&amp;&amp;', $result);
    }

    public function test_fenced_code_block_becomes_pre_code(): void
    {
        $result = $this->formatter()->format("Ini contohnya:\n```php\n\$x = 1;\n```");

        $this->assertStringContainsString('<pre><code>', $result);
        $this->assertStringContainsString('</code></pre>', $result);
        $this->assertStringContainsString('$x = 1;', $result);
    }

    public function test_inline_code_becomes_code_tag(): void
    {
        $result = $this->formatter()->format('Gunakan `array_map()` untuk ini.');

        $this->assertStringContainsString('<code>array_map()</code>', $result);
    }

    public function test_code_block_content_is_also_escaped(): void
    {
        $result = $this->formatter()->format("```\nif (a < b) { return; }\n```");

        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringContainsString('<pre><code>', $result);
    }
}
