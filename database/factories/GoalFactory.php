<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement([Goal::TYPE_WEEKLY, Goal::TYPE_MONTHLY]),
            'target_days' => fake()->numberBetween(3, 20),
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => (clone $startsOn)->modify('+1 month')->format('Y-m-d'),
            'active' => true,
        ];
    }
}
