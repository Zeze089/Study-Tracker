<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_study_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('content', 255);
            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();
            $table->string('timezone', 64);
            $table->string('priority', 10)->default('normal');
            $table->unsignedSmallInteger('planned_minutes');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'scheduled_date', 'scheduled_time'], 'planned_sessions_user_date_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_study_sessions');
    }
};
