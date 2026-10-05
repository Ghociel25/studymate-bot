<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns match DATABASE_DESIGN.md's `files` table exactly: id,
     * user_id, project_id nullable, conversation_id nullable,
     * original_name, mime_type, size, storage_path, processing_status,
     * created_at, updated_at.
     *
     * No column for extracted text: DATABASE_DESIGN.md doesn't have one,
     * so extracted text is stored as a companion file on the same private
     * disk next to the original (see FileService), not a DB column.
     *
     * project_id/conversation_id: nullOnDelete, same reasoning as
     * conversations.project_id (TASK-006) — removing a project/
     * conversation shouldn't destroy file history.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('storage_path');
            $table->string('processing_status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
