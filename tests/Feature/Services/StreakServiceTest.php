<?php

namespace Tests\Feature\Services;

use App\Models\StudyRecord;
use App\Models\User;
use App\Services\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_without_a_record_does_not_break_the_current_streak(): void
    {
        $user = User::factory()->create();
        $today = CarbonImmutable::parse('2026-09-05', $user->timezone);

        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'] as $date) {
            StudyRecord::factory()->create([
                'user_id' => $user->id,
                'subject_id' => null,
                'study_date' => $date,
                'studied' => true,
                'minutes' => null,
            ]);
        }

        $this->assertSame(4, app(StreakService::class)->currentStreak($user, $today));
    }

    public function test_a_past_day_without_a_record_breaks_the_current_streak(): void
    {
        $user = User::factory()->create();
        $today = CarbonImmutable::parse('2026-09-05', $user->timezone);

        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-05'] as $date) {
            StudyRecord::factory()->create([
                'user_id' => $user->id,
                'subject_id' => null,
                'study_date' => $date,
                'studied' => true,
                'minutes' => null,
            ]);
        }

        $this->assertSame(1, app(StreakService::class)->currentStreak($user, $today));
    }

    public function test_today_marked_as_not_studied_breaks_the_current_streak(): void
    {
        $user = User::factory()->create();
        $today = CarbonImmutable::parse('2026-09-05', $user->timezone);

        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'] as $date) {
            StudyRecord::factory()->create([
                'user_id' => $user->id,
                'subject_id' => null,
                'study_date' => $date,
                'studied' => true,
                'minutes' => null,
            ]);
        }

        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'subject_id' => null,
            'study_date' => '2026-09-05',
            'studied' => false,
            'minutes' => null,
        ]);

        $this->assertSame(0, app(StreakService::class)->currentStreak($user, $today));
    }
}
