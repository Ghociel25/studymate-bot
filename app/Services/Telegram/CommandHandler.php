<?php

namespace App\Services\Telegram;

use App\Exceptions\FileAccessDeniedException;
use App\Exceptions\ProjectAccessDeniedException;
use App\Models\User;
use App\Services\AI\FileActionInstructions;
use App\Services\File\FileActionService;
use App\Services\File\FileService;
use App\Services\Project\ProjectService;
use App\Support\Mode;
use InvalidArgumentException;

/**
 * Maps a command (or plain text) to a response string.
 *
 * TASK-002 scope (FR-001 /start) is unchanged below — start() and
 * fallback() are untouched. TASK-005 added mode-routed chat (chat()):
 * plain text or a recognized mode command goes through IntentRouter ->
 * ModeService -> ResponseFormatter, also untouched here. TASK-006 added
 * project commands (/project, /projects, /newproject), also untouched.
 * TASK-007 adds /file <id> <action> for FR-009 file actions, checked
 * before the "unrecognized command -> fallback" branch.
 */
class CommandHandler
{
    private const PROJECT_COMMANDS = ['/project', '/projects', '/newproject'];

    public function __construct(
        private readonly IntentRouter $intentRouter,
        private readonly ModeService $modeService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ProjectService $projectService,
        private readonly FileService $fileService,
        private readonly FileActionService $fileActionService,
    ) {
    }

    public function handle(User $user, ?string $command, string $rawText): string
    {
        if ($command === '/start') {
            return $this->start($user);
        }

        if ($command !== null && in_array($command, self::PROJECT_COMMANDS, true)) {
            return $this->project($user, $command, $rawText);
        }

        if ($command === '/file') {
            return $this->fileAction($user, $rawText);
        }

        // A slash command that isn't /start, a project/file command, or a
        // recognized mode command: preserve TASK-002's exact behavior
        // (controlled fallback), don't silently route it into general chat.
        if ($command !== null && Mode::fromCommand($command) === null) {
            return $this->fallback();
        }

        return $this->chat($user, $rawText);
    }

    private function fileAction(User $user, string $rawText): string
    {
        // rawText is the full message, e.g. "/file 5 ringkas" — strip the
        // command token, split the rest into [id, action].
        $arg = trim(substr($rawText, strlen('/file')));
        $parts = preg_split('/\s+/', $arg, 2);
        $fileId = $parts[0] ?? '';
        $action = $parts[1] ?? '';

        if (! ctype_digit($fileId) || $action === '') {
            $actions = implode(', ', FileActionInstructions::ACTIONS);

            return "Format: <code>/file &lt;id&gt; &lt;aksi&gt;</code>. Aksi yang tersedia: {$actions}.";
        }

        try {
            $file = $this->fileService->getOwned($user, (int) $fileId);
        } catch (FileAccessDeniedException) {
            return "File itu gak ditemukan atau bukan punya kamu.";
        }

        try {
            $response = $this->fileActionService->run($user, $file, strtolower($action));
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        if (! $response->success || $response->content === null) {
            return 'Maaf, ada masalah saat memproses file ini. Coba lagi sebentar lagi ya.';
        }

        return $this->responseFormatter->format($response->content);
    }

    private function project(User $user, string $command, string $rawText): string
    {
        $arg = trim(substr($rawText, strlen($command)));

        return match ($command) {
            '/newproject' => $this->newProject($user, $arg),
            '/projects' => $this->listProjects($user),
            '/project' => $this->showOrSwitchProject($user, $arg),
            default => $this->fallback(), // unreachable, keeps match() exhaustive
        };
    }

    private function newProject(User $user, string $name): string
    {
        if ($name === '') {
            return "Nama project gak boleh kosong. Contoh: <code>/newproject Tugas OOP</code>";
        }

        $project = $this->projectService->create($user, $name);
        $this->projectService->setActive($user, $project->id);

        return "Project <b>{$this->escape($project->name)}</b> dibuat dan jadi project aktif kamu sekarang.";
    }

    private function listProjects(User $user): string
    {
        $projects = $this->projectService->listForUser($user);

        if ($projects->isEmpty()) {
            return "Kamu belum punya project. Buat satu dengan <code>/newproject Nama Project</code>.";
        }

        $activeId = $user->active_project_id;
        $lines = $projects->map(function ($project) use ($activeId) {
            $marker = $project->id === $activeId ? '✅ ' : '';

            return "{$marker}#{$project->id} {$this->escape($project->name)} ({$project->status})";
        });

        return "<b>Project kamu:</b>\n".$lines->implode("\n")
            ."\n\nUntuk pindah project aktif: <code>/project &lt;id&gt;</code>";
    }

    private function showOrSwitchProject(User $user, string $arg): string
    {
        if ($arg === '') {
            $active = $this->projectService->getActive($user);

            if ($active === null) {
                return "Belum ada project aktif. Lihat daftar project dengan /projects, atau buat baru dengan <code>/newproject Nama Project</code>.";
            }

            $description = $active->description ? "\n{$this->escape($active->description)}" : '';

            return "<b>Project aktif:</b> {$this->escape($active->name)}{$description}";
        }

        if (! ctype_digit($arg)) {
            return "Format salah. Gunakan: <code>/project &lt;id&gt;</code> (lihat id lewat /projects).";
        }

        try {
            $project = $this->projectService->setActive($user, (int) $arg);
        } catch (ProjectAccessDeniedException) {
            return "Project itu gak ditemukan atau bukan punya kamu.";
        }

        return "Project aktif diganti ke <b>{$this->escape($project->name)}</b>.";
    }

    private function escape(string $text): string
    {
        return e($text);
    }

    private function chat(User $user, string $rawText): string
    {
        $intent = $this->intentRouter->route($rawText);

        if ($intent->input === '') {
            return "Mode ".$intent->mode->value." aktif. Kirim pertanyaan atau permintaanmu ya.";
        }

        $response = $this->modeService->handle($user, $intent->mode, $intent->input);

        if (! $response->success || $response->content === null) {
            // STYLE_GUIDE.md "Error Message": explain what happened and
            // the next step, never a stack trace.
            return 'Maaf, ada masalah saat memproses permintaanmu. Coba lagi sebentar lagi ya.';
        }

        return $this->responseFormatter->format($response->content);
    }

    private function start(User $user): string
    {
        $name = $user->first_name ?: 'there';

        return <<<TEXT
        Hai {$name}! 👋 Selamat datang di <b>StudyMate Bot</b>.

        Aku bisa bantu kamu coding, tugas, belajar, dan project kuliah lewat chat ini.

        <b>Menu utama:</b>
        💬 Chat AI
        💻 Coding
        📚 Belajar
        📝 Tugas
        📁 Project
        📄 File

        Fitur-fitur di atas masih dalam pengembangan bertahap — untuk sekarang, kirim /start kapan saja untuk melihat pesan ini lagi.
        TEXT;
    }

    private function fallback(): string
    {
        return "Bot masih dalam tahap pengembangan awal, fitur ini belum tersedia. Coba kirim /start dulu ya.";
    }
}
