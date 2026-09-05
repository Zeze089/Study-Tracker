<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            ['name' => 'Laravel', 'color' => '#ef4444'],
            ['name' => 'PHP', 'color' => '#6366f1'],
            ['name' => 'JavaScript', 'color' => '#eab308'],
            ['name' => 'Vue', 'color' => '#10b981'],
            ['name' => 'React', 'color' => '#06b6d4'],
            ['name' => 'Redes', 'color' => '#0f766e'],
            ['name' => 'Pentest', 'color' => '#7c3aed'],
            ['name' => 'Linux', 'color' => '#334155'],
            ['name' => 'Docker', 'color' => '#2563eb'],
            ['name' => 'Banco de Dados', 'color' => '#f97316'],
        ];

        User::query()->each(function (User $user) use ($subjects): void {
            foreach ($subjects as $subject) {
                Subject::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $subject['name']],
                    ['color' => $subject['color'], 'active' => true]
                );
            }
        });
    }
}
