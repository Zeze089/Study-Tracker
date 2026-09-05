<?php

namespace Tests\Feature\Services;

use App\Models\StudyRecord;
use App\Models\Subject;
use App\Models\User;
use App\Services\StudyStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudyStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_without_records_returns_zeroed_statistics_for_elapsed_days(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 12:00:00'));
        $user = User::factory()->create();

        $summary = app(StudyStatsService::class)->monthSummary($user);

        $this->assertSame(10, $summary['elapsed_days']);
        $this->assertSame(0, $summary['registered_days']);
        $this->assertSame(0, $summary['studied_days']);
        $this->assertSame(0, $summary['not_studied_days']);
        $this->assertSame(10, $summary['unregistered_days']);
        $this->assertSame(0, $summary['total_minutes']);
        $this->assertSame(0.0, $summary['total_hours']);
        $this->assertSame('0min', $summary['total_hours_label']);
        $this->assertSame(0, $summary['consistency']);
    }

    public function test_month_summary_counts_elapsed_days_and_excludes_future_days(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 12:00:00'));
        $user = User::factory()->create();

        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'study_date' => '2026-09-01',
            'studied' => true,
            'minutes' => 120,
        ]);
        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'study_date' => '2026-09-02',
            'studied' => true,
            'minutes' => null,
        ]);
        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'study_date' => '2026-09-04',
            'studied' => false,
            'minutes' => null,
        ]);
        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'study_date' => '2026-09-12',
            'studied' => true,
            'minutes' => 999,
        ]);

        $summary = app(StudyStatsService::class)->monthSummary($user);

        $this->assertSame(10, $summary['elapsed_days']);
        $this->assertSame(3, $summary['registered_days']);
        $this->assertSame(2, $summary['studied_days']);
        $this->assertSame(1, $summary['not_studied_days']);
        $this->assertSame(7, $summary['unregistered_days']);
        $this->assertSame(120, $summary['total_minutes']);
        $this->assertSame('2h', $summary['total_hours_label']);
        $this->assertSame(20, $summary['consistency']);
    }

    public function test_subject_ranking_uses_minutes_and_keeps_user_data_isolated(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 12:00:00'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $laravel = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel']);
        $php = Subject::factory()->create(['user_id' => $user->id, 'name' => 'PHP']);
        $secret = Subject::factory()->create(['user_id' => $otherUser->id, 'name' => 'Materia secreta']);

        StudyRecord::factory()->create(['user_id' => $user->id, 'subject_id' => $php->id, 'study_date' => '2026-09-01', 'studied' => true, 'minutes' => 240]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'subject_id' => $laravel->id, 'study_date' => '2026-09-02', 'studied' => true, 'minutes' => 600]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'subject_id' => null, 'study_date' => '2026-09-03', 'studied' => true, 'minutes' => 90]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'subject_id' => $laravel->id, 'study_date' => '2026-09-04', 'studied' => true, 'minutes' => null]);
        StudyRecord::factory()->create(['user_id' => $otherUser->id, 'subject_id' => $secret->id, 'study_date' => '2026-09-02', 'studied' => true, 'minutes' => 999]);

        $subjects = app(StudyStatsService::class)->subjectSummary($user);

        $this->assertSame('Laravel', $subjects[0]['name']);
        $this->assertSame(600, $subjects[0]['total_minutes']);
        $this->assertSame(2, $subjects[0]['records_count']);
        $this->assertSame('PHP', $subjects[1]['name']);
        $this->assertSame('Sem materia', $subjects[2]['name']);
        $this->assertSame(90, $subjects[2]['total_minutes']);
        $this->assertNotContains('Materia secreta', array_column($subjects, 'name'));
    }

    public function test_recent_chart_returns_the_last_seven_days_with_zeroes_and_isolated_data(): void
    {
        $this->travelTo(Carbon::parse('2026-09-07 12:00:00'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-09-01', 'studied' => true, 'minutes' => 120]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-09-03', 'studied' => true, 'minutes' => 60]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-09-04', 'studied' => false, 'minutes' => null]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-09-07', 'studied' => true, 'minutes' => 45]);
        StudyRecord::factory()->create(['user_id' => $otherUser->id, 'study_date' => '2026-09-05', 'studied' => true, 'minutes' => 999]);

        $chart = app(StudyStatsService::class)->recentChart($user);

        $this->assertCount(7, $chart);
        $this->assertSame('2026-09-01', $chart[0]['date']);
        $this->assertSame('2026-09-07', $chart[6]['date']);
        $this->assertSame([120, 0, 60, 0, 0, 0, 45], array_column($chart, 'minutes'));
    }

    public function test_recent_activity_returns_five_latest_records_without_other_users_data(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 12:00:00'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel']);
        $secret = Subject::factory()->create(['user_id' => $otherUser->id, 'name' => 'Materia secreta']);

        foreach (range(1, 6) as $day) {
            StudyRecord::factory()->create([
                'user_id' => $user->id,
                'subject_id' => $day === 6 ? null : $subject->id,
                'study_date' => '2026-09-0'.$day,
                'studied' => $day !== 5,
                'content' => $day === 6 ? 'Funcoes e estruturas de repeticao' : null,
                'minutes' => $day === 5 ? null : 30,
            ]);
        }
        StudyRecord::factory()->create(['user_id' => $otherUser->id, 'subject_id' => $secret->id, 'study_date' => '2026-09-10', 'studied' => true, 'minutes' => 999]);

        $activity = app(StudyStatsService::class)->recentActivity($user);

        $this->assertCount(5, $activity);
        $this->assertSame('2026-09-06', $activity[0]['date']);
        $this->assertSame('Sem materia', $activity[0]['subject_name']);
        $this->assertSame('Funcoes e estruturas de repeticao', $activity[0]['content']);
        $this->assertFalse($activity[1]['studied']);
        $this->assertNotContains('Materia secreta', array_column($activity, 'subject_name'));
    }
}
