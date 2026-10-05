<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns match DATABASE_DESIGN.md's `projects` table exactly:
     * id, user_id, name, description, status, created_at, updated_at.
     *
     * `status` values are not enumerated in DATABASE_DESIGN.md — this is
     * the implementation's own reasonable choice (active/completed/
     * archived), validated at the service layer (ProjectService), not the
     * DB, for portability (same pattern as Message::role in TASK-004).
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // Fulfils the TODO left in
        // 2025_01_02_000000_create_conversations_table.php (TASK-004):
        // projects now exists, so the FK can be added. nullOnDelete: a
        // deleted project shouldn't destroy conversation history.
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::dropIfExists('projects');
    }
};
