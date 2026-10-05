<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns match DATABASE_DESIGN.md's `conversations` table exactly:
     * id, user_id, project_id nullable, title nullable, created_at,
     * updated_at.
     *
     * `project_id` has no foreign key constraint yet: the `projects`
     * table doesn't exist until TASK-006. Add the constraint there once
     * it does, the same way this migration adds the `ai_requests.
     * conversation_id` constraint that TASK-003 deferred to this task.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index('project_id');
        });

        // Fulfils the TODO left in 2025_01_01_000000_create_ai_requests_table.php
        // (TASK-003): conversations now exists, so the FK can be added.
        Schema::table('ai_requests', function (Blueprint $table) {
            $table->foreign('conversation_id')
                ->references('id')->on('conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_requests', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });

        Schema::dropIfExists('conversations');
    }
};
