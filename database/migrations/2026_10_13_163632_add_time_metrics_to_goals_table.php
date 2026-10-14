<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->string('metric', 20)->default('days');
            $table->unsignedInteger('target_minutes')->nullable();
            $table->index(['user_id', 'type', 'metric', 'active'], 'goals_user_type_metric_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->dropIndex('goals_user_type_metric_active_index');
            $table->dropColumn(['metric', 'target_minutes']);
        });
    }
};
