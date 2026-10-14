<?php

namespace Tests\Feature;

use App\Models\PlannedStudySession;
use App\Models\StudyRecord;
use App\Services\WeeklyScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlanningConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'America/Fortaleza'));
    }

    public function test_execution_cannot_create_a_day_that_the_existing_editor_rejects(): void
    {
        $session = PlannedStudySession::factory()->create();
        $record = StudyRecord::factory()->create(['user_id' => $session->user_id, 'study_date' => '2026-10-14', 'studied' => true, 'minutes' => 1]);
        for ($position = 2; $position <= 12; $position++) {
            $record->items()->create(['minutes' => 1, 'position' => $position]);
        }
        $record->update(['minutes' => 12]);
        $this->actingAs($session->user)->postJson(route('schedule.execute', $session), [
            'study_date' => '2026-10-14', 'actual_minutes' => 1, 'submission_key' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('actual_minutes');
        $this->assertSame(12, $record->items()->count());
        $this->assertSame(12, $record->fresh()->minutes);
        $this->assertDatabaseCount('planned_execution_submissions', 0);
        $items = $record->items()->get()->map(fn ($item): array => ['id' => $item->id, 'minutes' => $item->minutes])->all();
        $this->putJson(route('study-records.update', $record), ['study_date' => '2026-10-14', 'studied' => true, 'items' => $items])->assertOk();
    }

    public function test_history_and_cancellation_dates_use_the_sessions_saved_timezone(): void
    {
        $session = PlannedStudySession::factory()->create(['timezone' => 'Pacific/Auckland', 'scheduled_date' => '2026-10-16']);
        $this->actingAs($session->user)->patch(route('schedule.reschedule', $session), [
            'scheduled_date' => '2026-10-17', 'scheduled_time' => '09:00', 'reason' => 'Ajuste',
            'revision' => $session->revision(),
        ])->assertRedirect();
        $this->get(route('schedule.index'))->assertOk()->assertSee('15/10/2026 04:00');
        $this->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertRedirect();
        $this->get(route('schedule.index'))->assertOk()->assertSee('Cancelada em 15/10/2026');
    }

    public function test_progress_accessibility_distinguishes_real_minutes_from_the_planned_target(): void
    {
        $session = PlannedStudySession::factory()->create(['planned_minutes' => 60]);
        $this->actingAs($session->user)->get(route('schedule.index'))->assertOk()
            ->assertSee('0 minutos realizados de 60 minutos previstos');
    }

    public function test_month_and_week_boundaries_include_the_last_day_and_exclude_the_next_day(): void
    {
        $session = PlannedStudySession::factory()->create(['scheduled_date' => '2026-10-18']);
        PlannedStudySession::factory()->create(['subject_id' => $session->subject_id, 'scheduled_date' => '2026-10-19']);
        $week = app(WeeklyScheduleService::class)->week($session->user, '2026-10-12');
        $this->assertSame(1, $week['active_count']);
        $this->assertSame($session->id, $week['days'][6]['sessions']->first()->id);
    }
}
