<?php

namespace App\Services\Telegram;

use App\Models\File;
use App\Models\User;
use App\Services\AI\FileActionInstructions;
use App\Services\File\FileService;
use App\Services\Project\ProjectService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Orchestrates one inbound Telegram update, end to end. Kept out of the
 * controller per ARCHITECTURE.md ("Controller hanya orchestration request
 * level. Business logic berada di Service.").
 *
 * TASK-002: handle() validates+processes text messages (pipeline below
 * untouched). TASK-007 adds a document-message branch, checked first,
 * delegating to handleDocument().
 */
class WebhookHandler
{
    public function __construct(
        private readonly UpdateValidator $validator,
        private readonly MessageProcessor $processor,
        private readonly CommandHandler $commandHandler,
        private readonly TelegramApiClient $telegramApi,
        private readonly FileService $fileService,
        private readonly ProjectService $projectService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return bool  true if the payload was a supported update and was processed
     */
    public function handle(array $payload): bool
    {
        if ($this->validator->isSupportedDocumentMessage($payload)) {
            return $this->handleDocument($payload);
        }

        if (! $this->validator->isSupportedTextMessage($payload)) {
            Log::info('telegram.webhook.unsupported_update', [
                'update_id' => $payload['update_id'] ?? null,
            ]);

            return false;
        }

        $message = $this->processor->process($payload);

        // Ownership/identity: find or create the local user by telegram_id.
        // Never trust anything from the payload beyond identity fields.
        $user = User::firstOrCreate(
            ['telegram_id' => $message->telegramUserId],
            [
                'username' => $message->username,
                'first_name' => $message->firstName,
                'last_name' => $message->lastName,
            ]
        );

        Log::info('telegram.webhook.message_received', [
            'update_id' => $payload['update_id'],
            'chat_id' => $message->chatId,
            'user_id' => $user->id,
            'command' => $message->command,
        ]);

        $reply = $this->commandHandler->handle($user, $message->command, $message->text);

        $this->telegramApi->sendMessage($message->chatId, $reply);

        return true;
    }

    /**
     * FILE_PROCESSING.md pipeline: validate user+size+MIME -> download ->
     * private storage -> extract -> status -> reply. Mirrors the user
     * find-or-create logic above independently, rather than extracting a
     * shared helper — keeps this new path from risking any change to the
     * proven text-message flow.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleDocument(array $payload): bool
    {
        $doc = $this->processor->processDocument($payload);

        $user = User::firstOrCreate(
            ['telegram_id' => $doc->telegramUserId],
            ['username' => $doc->username, 'first_name' => $doc->firstName, 'last_name' => $doc->lastName]
        );

        Log::info('telegram.webhook.document_received', [
            'update_id' => $payload['update_id'],
            'chat_id' => $doc->chatId,
            'user_id' => $user->id,
            'file_name' => $doc->fileName,
        ]);

        try {
            $this->fileService->validate($doc->fileName, $doc->fileSize, $doc->mimeType);
        } catch (InvalidArgumentException $e) {
            $this->telegramApi->sendMessage($doc->chatId, $e->getMessage());

            return true;
        }

        $filePath = $this->telegramApi->getFileInfo($doc->fileId);

        if ($filePath === null) {
            $this->telegramApi->sendMessage($doc->chatId, 'Gagal mengambil file dari Telegram. Coba kirim ulang ya.');

            return true;
        }

        $bytes = $this->telegramApi->downloadFile($filePath);

        if ($bytes === null) {
            $this->telegramApi->sendMessage($doc->chatId, 'Gagal mengunduh file. Coba kirim ulang ya.');

            return true;
        }

        try {
            $file = $this->fileService->store(
                $user,
                $doc->fileName,
                $doc->mimeType,
                $bytes,
                projectId: $this->projectService->getActive($user)?->id,
            );
        } catch (InvalidArgumentException $e) {
            $this->telegramApi->sendMessage($doc->chatId, $e->getMessage());

            return true;
        }

        $this->telegramApi->sendMessage($doc->chatId, $this->fileReceivedMessage($file));

        return true;
    }

    private function fileReceivedMessage(File $file): string
    {
        $name = e($file->original_name);

        if ($file->processing_status !== 'processed') {
            return "File <b>{$name}</b> diterima (id #{$file->id}), tapi belum bisa diproses otomatis untuk tipe ini. File tetap tersimpan aman.";
        }

        $actions = implode(', ', FileActionInstructions::ACTIONS);

        return "File <b>{$name}</b> diterima dan sudah diproses (id #{$file->id}).\n\n"
            ."Gunakan <code>/file {$file->id} &lt;aksi&gt;</code> untuk minta bantuan AI. Aksi: {$actions}.\n"
            ."Contoh: <code>/file {$file->id} ringkas</code>";
    }
}
