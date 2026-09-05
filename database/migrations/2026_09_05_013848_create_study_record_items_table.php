<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('study_record_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('content')->nullable();
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();

            $table->index(['study_record_id', 'position']);
            $table->index(['subject_id', 'study_record_id']);
        });

        DB::table('study_records')
            ->where(function ($query): void {
                $query
                    ->whereNotNull('subject_id')
                    ->orWhereNotNull('content')
                    ->orWhereNotNull('minutes');
            })
            ->orderBy('id')
            ->chunkById(500, function ($records): void {
                $items = $records->map(fn ($record): array => [
                    'study_record_id' => $record->id,
                    'subject_id' => $record->subject_id,
                    'content' => $record->content,
                    'minutes' => $record->minutes,
                    'position' => 1,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                ])->all();

                DB::table('study_record_items')->insert($items);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_record_items');
    }
};
