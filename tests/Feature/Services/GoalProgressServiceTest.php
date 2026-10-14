<?php

namespace Tests\Feature\Services;

use App\Models\Goal;
use App\Models\StudyRecord;
use App\Models\User;
use App\Services\GoalProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoalProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_counts_only_own_studied_days_within_the_period_until_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00'));
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, 'type' => 'weekly', 'target_days' => 2, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18']);
        foreach (['2026-10-11', '2026-10-12', '2026-10-13', '2026-10-15', '2026-10-19'] as $date) {
            StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => $date, 'studied' => true, 'minutes' => null]);
        }
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-14', 'studied' => false]);
        StudyRecord::factory()->create(['user_id' => User::factory()->create()->id, 'study_date' => '2026-10-14', 'studied' => true]);

        $service = app(GoalProgressService::class);
        $progress = $service->progressForGoal($goal);
        $this->assertSame(2, $progress['studied_days']);
        $this->assertSame(100, $progress['percentage']);
        $this->assertSame(0, $progress['remaining_days']);
        $this->assertSame('Atingida', $progress['status']);

        StudyRecord::query()->where('user_id', $user->id)->whereDate('study_date', '2026-10-13')->update(['studied' => false]);
        $this->assertSame(1, $service->progressForGoal($goal)['studied_days']);
    }

    public function test_progress_includes_both_boundaries_and_caps_only_the_percentage(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, 'target_days' => 1, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18']);
        foreach (['2026-10-12', '2026-10-18'] as $date) {
            StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => $date, 'studied' => true]);
        }
        $progress = app(GoalProgressService::class)->progressForGoal($goal, CarbonImmutable::parse('2026-10-19', $user->timezone));
        $this->assertSame(2, $progress['studied_days']);
        $this->assertSame(100, $progress['percentage']);
    }

    public function test_goal_states_and_local_today_use_the_users_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 01:00:00', 'UTC'));
        $user = User::factory()->create(['timezone' => 'America/Fortaleza']);
        $goal = Goal::factory()->create(['user_id' => $user->id, 'target_days' => 4, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'active' => true]);
        $future = Goal::factory()->create(['user_id' => $user->id, 'target_days' => 4, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'active' => true]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-19', 'studied' => true]);

        $service = app(GoalProgressService::class);
        $this->assertCount(1, $service->activeProgress($user));
        $this->assertSame('Em andamento', $service->progressForGoal($goal)['status']);
        $this->assertSame('Planejada', $service->progressForGoal($future)['status']);
        $this->assertSame(0, $service->progressForGoal($future)['studied_days']);
        $this->travel(3)->hours();
        $this->assertSame('Encerrada', $service->progressForGoal($goal)['status']);
        $goal->update(['active' => false]);
        $this->assertSame('Arquivada', $service->progressForGoal($goal)['status']);
    }

    public function test_paginated_progress_uses_constant_query_count_for_multiple_goals(): void
    {
        $user = User::factory()->create();
        Goal::factory()->count(12)->create(['user_id' => $user->id]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $page = app(GoalProgressService::class)->pageForUser($user);

        $this->assertCount(12, $page->items());
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
