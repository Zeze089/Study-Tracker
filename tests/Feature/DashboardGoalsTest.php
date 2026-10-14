<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\StudyRecord;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardGoalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_integrates_goals_weekly_minutes_and_streak_without_double_counting(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'America/Fortaleza'));
        $user = User::factory()->create();
        foreach (['2026-10-11' => 300, '2026-10-12' => 30, '2026-10-13' => 45, '2026-10-14' => 60, '2026-10-15' => 500] as $date => $minutes) {
            StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => $date, 'studied' => true, 'minutes' => $minutes]);
        }
        Goal::factory()->create(['user_id' => $user->id, 'type' => 'weekly', 'metric' => 'minutes', 'target_days' => 0, 'target_minutes' => 180, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'active' => true]);
        Goal::factory()->create(['user_id' => $user->id, 'type' => 'daily', 'metric' => 'days', 'target_days' => 1, 'starts_on' => '2026-10-14', 'ends_on' => '2026-10-14', 'active' => true]);

        $response = $this->actingAs($user)->getJson(route('dashboard.stats'));
        $response->assertOk()
            ->assertJsonPath('study_indicators.daily.minutes', 60)
            ->assertJsonPath('study_indicators.weekly.minutes', 135)
            ->assertJsonPath('study_indicators.weekly.duration_label', '2h 15min')
            ->assertJsonPath('study_indicators.weekly.studied_days', 3)
            ->assertJsonPath('study_indicators.weekly.starts_on', '2026-10-12')
            ->assertJsonPath('study_indicators.weekly.ends_on', '2026-10-18')
            ->assertJsonPath('current_streak', 4)
            ->assertJsonPath('longest_streak', 4)
            ->assertJsonPath('goals.total', 2)
            ->assertJsonPath('goals.achieved', 1)
            ->assertJsonPath('goals.ongoing', 1);
        $weekly = collect($response->json('goals.items'))->firstWhere('type', 'weekly');
        $this->assertSame(75, $weekly['percentage']);
        $this->assertSame('2h 15min', $weekly['achieved_label']);
        $this->assertArrayNotHasKey('user_id', $weekly);
        $this->get(route('dashboard'))->assertOk()->assertSee('Metas atuais')->assertSee('Tempo nesta semana')->assertSee('Sequencia atual');
    }

    public function test_dashboard_goals_are_isolated_and_exclude_archived_ended_and_planned_goals(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00'));
        $user = User::factory()->create();
        $other = User::factory()->create();
        $base = ['type' => 'daily', 'metric' => 'days', 'target_days' => 1, 'starts_on' => '2026-10-14', 'ends_on' => '2026-10-14', 'active' => true];
        $own = Goal::factory()->create(['user_id' => $user->id, ...$base]);
        Goal::factory()->create(['user_id' => $other->id, ...$base]);
        Goal::factory()->create(['user_id' => $user->id, ...$base, 'active' => false]);
        Goal::factory()->create(['user_id' => $user->id, ...$base, 'starts_on' => '2026-10-13', 'ends_on' => '2026-10-13']);
        Goal::factory()->create(['user_id' => $user->id, ...$base, 'starts_on' => '2026-10-15', 'ends_on' => '2026-10-15']);
        StudyRecord::factory()->create(['user_id' => $other->id, 'study_date' => '2026-10-14', 'studied' => true, 'minutes' => 999]);
        $this->actingAs($user)->getJson(route('dashboard.stats'))->assertOk()
            ->assertJsonPath('goals.total', 1)->assertJsonPath('goals.items.0.id', $own->id)
            ->assertJsonPath('goals.items.0.percentage', 0)->assertJsonPath('study_indicators.weekly.minutes', 0)
            ->assertJsonPath('current_streak', 0);
    }

    public function test_saved_study_refreshes_goal_progress_and_daily_weekly_indicators(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00'));
        $user = User::factory()->create();
        Goal::factory()->create(['user_id' => $user->id, 'type' => 'daily', 'metric' => 'minutes', 'target_days' => 0, 'target_minutes' => 90, 'starts_on' => '2026-10-14', 'ends_on' => '2026-10-14', 'active' => true]);
        $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-10-14', 'studied' => true,
            'items' => [['minutes' => 30], ['minutes' => 60]],
        ])->assertCreated();
        $this->getJson(route('dashboard.stats'))->assertOk()->assertJsonPath('goals.items.0.percentage', 100)
            ->assertJsonPath('goals.achieved', 1)->assertJsonPath('study_indicators.daily.minutes', 90)
            ->assertJsonPath('study_indicators.weekly.minutes', 90)->assertJsonPath('current_streak', 1);
    }

    public function test_local_sunday_and_monday_define_week_boundaries_across_years(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-04 01:00:00', 'UTC'));
        $user = User::factory()->create(['timezone' => 'America/Fortaleza']);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-12-28', 'studied' => true, 'minutes' => 30]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2027-01-03', 'studied' => true, 'minutes' => 60]);
        $service = app(DashboardService::class);
        $stats = $service->stats($user);
        $this->assertSame('2026-12-28', $stats['study_indicators']['weekly']['starts_on']);
        $this->assertSame('2027-01-03', $stats['study_indicators']['weekly']['ends_on']);
        $this->assertSame(90, $stats['study_indicators']['weekly']['minutes']);
        $this->travel(4)->hours();
        $stats = $service->stats($user);
        $this->assertSame('2027-01-04', $stats['study_indicators']['weekly']['starts_on']);
        $this->assertSame(0, $stats['study_indicators']['weekly']['minutes']);
        $this->assertSame(1, $stats['current_streak']);
    }

    public function test_dashboard_queries_do_not_grow_with_goal_count(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00'));
        $user = User::factory()->create();
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-14', 'studied' => true]);
        $service = app(DashboardService::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service->stats($user);
        $baseline = count(DB::getQueryLog());
        Goal::factory()->count(20)->create(['user_id' => $user->id, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'active' => true]);
        DB::flushQueryLog();
        $stats = $service->stats($user);
        $this->assertSame(20, $stats['goals']['total']);
        $this->assertCount($baseline, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_new_users_have_zeroed_goal_and_weekly_indicators(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('dashboard.stats'))->assertOk()
            ->assertJsonPath('goals.total', 0)->assertJsonPath('goals.items', [])
            ->assertJsonPath('study_indicators.daily.minutes', 0)
            ->assertJsonPath('study_indicators.weekly.studied_days', 0);
    }
}
