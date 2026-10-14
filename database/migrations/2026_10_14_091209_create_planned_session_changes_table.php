<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_session_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('planned_study_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('action', 20);
            $table->json('before');
            $table->json('after');
            $table->string('reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['planned_study_session_id', 'id'], 'planned_session_changes_session_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_session_changes');
    }
};
