<?php

namespace Database\Factories;

use App\Models\PlannedStudySession;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannedStudySession> */
class PlannedStudySessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'user_id' => fn (array $attributes): int => Subject::findOrFail($attributes['subject_id'])->user_id,
            'content' => fake()->sentence(4),
            'scheduled_date' => now('America/Fortaleza')->toDateString(),
            'scheduled_time' => '09:00',
            'timezone' => 'America/Fortaleza',
            'priority' => PlannedStudySession::PRIORITY_NORMAL,
            'planned_minutes' => 60,
            'cancelled_at' => null,
        ];
    }
}
