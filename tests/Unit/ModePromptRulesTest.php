<?php

namespace Tests\Unit;

use App\Services\AI\ModePromptRules;
use App\Support\Mode;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=ModePromptRulesTest`.
 */
class ModePromptRulesTest extends TestCase
{
    public function test_general_mode_has_no_addendum(): void
    {
        $this->assertSame([], ModePromptRules::forMode(Mode::General));
    }

    public function test_coding_mode_mentions_not_claiming_execution(): void
    {
        $rules = ModePromptRules::forMode(Mode::Coding);

        $this->assertCount(1, $rules);
        $this->assertSame('system', $rules[0]['role']);
        $this->assertStringContainsString('dijalankan', $rules[0]['content']);
    }

    public function test_assignment_mode_mentions_requirement_and_approach(): void
    {
        $rules = ModePromptRules::forMode(Mode::Assignment);

        $this->assertStringContainsString('requirement', $rules[0]['content']);
    }

    public function test_study_mode_mentions_step_by_step_and_examples(): void
    {
        $rules = ModePromptRules::forMode(Mode::Study);

        $this->assertStringContainsString('bertahap', $rules[0]['content']);
        $this->assertStringContainsString('contoh', $rules[0]['content']);
    }
}
