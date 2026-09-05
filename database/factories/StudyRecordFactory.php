<?php

namespace Database\Factories;

use App\Models\StudyRecord;
use App\Models\StudyRecordItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyRecord>
 */
class StudyRecordFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (StudyRecord $studyRecord): void {
            if (
                ! $studyRecord->studied
                || ($studyRecord->subject_id === null && $studyRecord->content === null && $studyRecord->minutes === null)
            ) {
                return;
            }

            StudyRecordItem::create([
                'study_record_id' => $studyRecord->id,
                'subject_id' => $studyRecord->subject_id,
                'content' => $studyRecord->content,
                'minutes' => $studyRecord->minutes,
                'position' => 1,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $studied = fake()->boolean(75);

        return [
            'user_id' => User::factory(),
            'subject_id' => null,
            'study_date' => fake()->dateTimeBetween('-90 days', 'today')->format('Y-m-d'),
            'studied' => $studied,
            'content' => $studied ? fake()->optional(0.55)->sentence(3) : null,
            'minutes' => $studied ? fake()->numberBetween(20, 240) : null,
            'notes' => fake()->optional(0.35)->sentence(),
        ];
    }
}
