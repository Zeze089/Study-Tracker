<?php

namespace Database\Factories;

use App\Models\StudyRecord;
use App\Models\StudyRecordItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyRecordItem>
 */
class StudyRecordItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_record_id' => StudyRecord::factory(),
            'subject_id' => null,
            'content' => fake()->optional(0.55)->sentence(3),
            'minutes' => fake()->optional(0.8)->numberBetween(20, 180),
            'position' => 1,
        ];
    }
}
