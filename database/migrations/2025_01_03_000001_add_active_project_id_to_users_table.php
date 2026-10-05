<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Implements SRS FR-012 "Active Project": a user can have one active
     * project, used for AI request context (FR-013). DATABASE_DESIGN.md's
     * original `users` column list (telegram_id, username, first_name,
     * last_name) didn't include this — FR-012 requires it but the schema
     * doc hadn't caught up yet (projects didn't exist before this task).
     * DATABASE_DESIGN.md is updated alongside this migration, not after.
     *
     * nullOnDelete: deleting the active project shouldn't break the user
     * row, just clear their active selection.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('active_project_id')
                ->nullable()
                ->after('last_name')
                ->constrained('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_project_id']);
            $table->dropColumn('active_project_id');
        });
    }
};
