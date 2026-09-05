<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->randomElement([
                'Laravel',
                'PHP',
                'JavaScript',
                'Vue',
                'React',
                'Redes',
                'Pentest',
                'Linux',
                'Docker',
                'Banco de Dados',
            ]),
            'color' => fake()->hexColor(),
            'active' => true,
        ];
    }
}
