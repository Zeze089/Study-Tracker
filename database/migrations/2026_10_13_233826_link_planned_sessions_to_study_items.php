<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_record_items', function (Blueprint $table): void {
            $table->foreignId('planned_study_session_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('planned_execution_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('planned_study_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_record_item_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('submission_key');
            $table->char('payload_hash', 64);
            $table->timestamps();
            $table->unique(['user_id', 'submission_key'], 'planned_execution_user_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_execution_submissions');
        Schema::table('study_record_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('planned_study_session_id');
        });
    }
};
