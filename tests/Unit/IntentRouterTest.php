<?php

namespace Tests\Unit;

use App\Services\Telegram\IntentRouter;
use App\Support\Mode;
use Tests\TestCase;

/**
 * NOTE: not executed by the agent — same sandbox constraint as previous
 * tasks. Run with `php artisan test --filter=IntentRouterTest`.
 */
class IntentRouterTest extends TestCase
{
    private function router(): IntentRouter
    {
        return new IntentRouter;
    }

    public function test_plain_text_defaults_to_general_mode(): void
    {
        $intent = $this->router()->route('halo, apa kabar?');

        $this->assertSame(Mode::General, $intent->mode);
        $this->assertSame('halo, apa kabar?', $intent->input);
    }

    public function test_coding_command_routes_to_coding_mode(): void
    {
        $intent = $this->router()->route('/coding jelaskan array di php');

        $this->assertSame(Mode::Coding, $intent->mode);
        $this->assertSame('jelaskan array di php', $intent->input);
    }

    public function test_tugas_and_assignment_both_route_to_assignment_mode(): void
    {
        $this->assertSame(Mode::Assignment, $this->router()->route('/tugas bantu analisis soal ini')->mode);
        $this->assertSame(Mode::Assignment, $this->router()->route('/assignment bantu analisis soal ini')->mode);
    }

    public function test_belajar_and_study_both_route_to_study_mode(): void
    {
        $this->assertSame(Mode::Study, $this->router()->route('/belajar jelaskan rekursi')->mode);
        $this->assertSame(Mode::Study, $this->router()->route('/study jelaskan rekursi')->mode);
    }

    public function test_chat_command_routes_to_general_mode(): void
    {
        $intent = $this->router()->route('/chat halo lagi');

        $this->assertSame(Mode::General, $intent->mode);
        $this->assertSame('halo lagi', $intent->input);
    }

    public function test_mode_command_with_no_trailing_text_has_empty_input(): void
    {
        $intent = $this->router()->route('/coding');

        $this->assertSame(Mode::Coding, $intent->mode);
        $this->assertSame('', $intent->input);
    }
}
