<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns match DATABASE_DESIGN.md's `ai_requests` table exactly:
     * id, user_id, conversation_id nullable, mode, provider, model,
     * input_tokens nullable, output_tokens nullable, status, error_code
     * nullable, created_at (no updated_at — rows are append-only records
     * of a single request, not mutated afterward).
     *
     * `conversation_id` has no foreign key constraint yet: the
     * `conversations` table doesn't exist until TASK-004. The FK
     * constraint should be added in TASK-004's migration once that table
     * exists, via Schema::table('ai_requests', ...)->foreign(...).
     */
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('mode');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('status');
            $table->string('error_code')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
