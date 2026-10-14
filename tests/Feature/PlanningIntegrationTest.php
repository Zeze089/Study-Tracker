<?php

namespace Tests\Feature;

use App\Models\PlannedStudySession;
use App\Models\StudyRecord;
use App\Models\StudyRecordItem;
use App\Models\Subject;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\WeeklyScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'America/Fortaleza'));
    }

    public function test_dashboard_separates_planned_linked_and_unplanned_minutes(): void
    {
        $session = PlannedStudySession::factory()->create(['scheduled_date' => '2026-10-14', 'scheduled_time' => '18:00', 'planned_minutes' => 120]);
        $user = $session->user;
        $record = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-14', 'studied' => true, 'minutes' => 30]);
        StudyRecordItem::factory()->create(['study_record_id' => $record->id, 'planned_study_session_id' => $session->id, 'subject_id' => $session->subject_id, 'minutes' => 45, 'position' => 2]);
        $record->update(['minutes' => 75]);
        PlannedStudySession::factory()->create(['subject_id' => $session->subject_id, 'scheduled_date' => '2026-10-15', 'planned_minutes' => 60, 'cancelled_at' => now()]);
        $this->actingAs($user)->getJson(route('dashboard.stats'))->assertOk()
            ->assertJsonPath('planning.planned_minutes', 120)
            ->assertJsonPath('study_indicators.weekly.linked_minutes', 45)
            ->assertJsonPath('study_indicators.weekly.unplanned_minutes', 30)
            ->assertJsonPath('study_indicators.weekly.minutes', 75)
            ->assertJsonPath('planning.upcoming.0.id', $session->id);
        $this->get(route('dashboard'))->assertOk()->assertSee('Estudos avulsos na semana')->assertSee('Previsto nesta semana');
    }

    public function test_dashboard_upcoming_overdue_and_counts_are_scoped_to_the_user(): void
    {
        $subject = Subject::factory()->create();
        $late = PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-13']);
        $upcoming = PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-15']);
        PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-30']);
        PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'cancelled_at' => now()]);
        PlannedStudySession::factory()->create(['content' => 'Segredo de outro usuario']);
        $this->actingAs($subject->user)->getJson(route('dashboard.stats'))->assertOk()
            ->assertJsonPath('planning.pending_count', 2)
            ->assertJsonPath('planning.overdue_count', 1)
            ->assertJsonPath('planning.overdue.0.id', $late->id)
            ->assertJsonPath('planning.upcoming.0.id', $upcoming->id)
            ->assertJsonMissing(['content' => 'Segredo de outro usuario']);
    }

    public function test_calendar_marks_future_plans_without_unlocking_future_execution(): void
    {
        $session = PlannedStudySession::factory()->create(['scheduled_date' => '2026-10-20']);
        $user = $session->user;
        $response = $this->actingAs($user)->get(route('calendar.index', ['month' => 10, 'year' => 2026]))->assertOk();
        $days = collect($response->viewData('calendar')['weeks'])->flatten(1)->filter()->keyBy('date');
        $this->assertSame(1, $days['2026-10-20']['planned_count']);
        $this->assertTrue($days['2026-10-20']['is_future']);
        $this->assertNull($days['2026-10-20']['record']);
        $response->assertSee('Planejar estudos em 20/10/2026')->assertSee('Ainda nao e possivel registrar esta data.');
        $this->get(route('schedule.index', ['week' => '2026-10-20', 'date' => '2026-10-20']))->assertOk()->assertSee('value="2026-10-20"', false);
        $this->postJson(route('study-records.store'), ['study_date' => '2026-10-20', 'studied' => true])->assertUnprocessable();
        $this->postJson(route('schedule.execute', $session), ['study_date' => '2026-10-20', 'actual_minutes' => 30, 'submission_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('study_records', 0);
    }

    public function test_calendar_markers_exclude_cancelled_and_other_users_sessions(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id]);
        PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-13']);
        PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-20', 'cancelled_at' => now()]);
        PlannedStudySession::factory()->create(['scheduled_date' => '2026-10-20']);
        $month = app(CalendarService::class)->month($user, 2026, 10);
        $days = collect($month['weeks'])->flatten(1)->filter()->keyBy('date');
        $this->assertSame(1, $days['2026-10-13']['overdue_count']);
        $this->assertSame(0, $days['2026-10-20']['planned_count']);
        $this->assertSame(0, $month['summary']['total_minutes']);
    }

    public function test_completed_session_disappears_from_pending_but_real_time_remains(): void
    {
        $session = PlannedStudySession::factory()->create(['planned_minutes' => 30]);
        $user = $session->user;
        $this->actingAs($user)->postJson(route('schedule.execute', $session), [
            'study_date' => '2026-10-14', 'actual_minutes' => 45, 'submission_key' => (string) Str::uuid(),
        ])->assertCreated();
        $this->getJson(route('dashboard.stats'))->assertOk()->assertJsonPath('planning.pending_count', 0)
            ->assertJsonPath('planning.overdue_count', 0)->assertJsonPath('study_indicators.weekly.linked_minutes', 45);
    }

    public function test_read_integrations_do_not_write_data_and_use_constant_queries(): void
    {
        $subject = Subject::factory()->create();
        PlannedStudySession::factory()->count(20)->create(['subject_id' => $subject->id]);
        $user = $subject->user;
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(WeeklyScheduleService::class)->dashboardData($user);
        $this->assertCount(2, DB::getQueryLog());
        DB::flushQueryLog();
        app(CalendarService::class)->month($user, 2026, 10);
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertDatabaseCount('study_records', 0);
        $this->assertDatabaseCount('study_record_items', 0);
        $this->assertDatabaseCount('planned_session_changes', 0);
    }

    public function test_dashboard_and_calendar_require_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('calendar.index'))->assertRedirect(route('login'));
        $this->getJson(route('dashboard.stats'))->assertUnauthorized();
        $this->getJson(route('calendar.index'))->assertUnauthorized();
    }

    public function test_read_only_markers_refresh_after_execution_without_exposing_other_users_plans(): void
    {
        $session = PlannedStudySession::factory()->create(['scheduled_date' => '2026-10-14', 'scheduled_time' => '09:00', 'planned_minutes' => 30]);
        PlannedStudySession::factory()->create(['scheduled_date' => '2026-10-20']);
        $this->actingAs($session->user);
        $url = route('calendar.index', ['year' => 2026, 'month' => 10]);
        $response = $this->getJson($url)->assertOk()->assertJsonPath('planning.2026-10-14.overdue_count', 1);
        $this->assertNull($response->json('planning.2026-10-20'));
        $this->assertDatabaseCount('study_records', 0);
        $this->postJson(route('schedule.execute', $session), [
            'study_date' => '2026-10-14', 'actual_minutes' => 30, 'submission_key' => (string) Str::uuid(),
        ])->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonPath('planning.2026-10-14.overdue_count', 0);
    }
}
