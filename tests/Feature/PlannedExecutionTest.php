<?php

namespace Tests\Feature;

use App\Models\PlannedStudySession;
use App\Models\StudyRecord;
use App\Models\User;
use App\Services\WeeklyScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlannedExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00'));
    }

    /** @return array<string, mixed> */
    private function payload(int $minutes = 30): array
    {
        return ['study_date' => '2026-10-13', 'actual_minutes' => $minutes, 'submission_key' => (string) Str::uuid()];
    }

    public function test_execution_appends_to_existing_day_preserving_ids_notes_and_actual_totals(): void
    {
        $session = PlannedStudySession::factory()->create(['planned_minutes' => 120]);
        $record = StudyRecord::factory()->create(['user_id' => $session->user_id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 45, 'notes' => 'Preservar']);
        $old = $record->items()->firstOrFail();
        $this->actingAs($session->user)->postJson(route('schedule.execute', $session), $this->payload())->assertCreated();
        $this->assertDatabaseHas('study_record_items', ['id' => $old->id, 'minutes' => 45]);
        $this->assertDatabaseHas('study_record_items', ['study_record_id' => $record->id, 'planned_study_session_id' => $session->id, 'minutes' => 30]);
        $this->assertSame(75, $record->fresh()->minutes);
        $this->assertSame('Preservar', $record->fresh()->notes);
        $this->assertSame(2, $record->items()->count());
        $this->assertDatabaseCount('study_records', 1);
        $progress = app(WeeklyScheduleService::class)->week($session->user)['days'][1]['sessions']->first();
        $this->assertSame(30, (int) $progress->real_minutes);
        $this->assertSame(25, $progress->execution_percentage);
        $this->assertSame('Parcial', $progress->execution_status);
    }

    public function test_duplicate_submission_is_idempotent_and_changed_payload_is_rejected(): void
    {
        $session = PlannedStudySession::factory()->create();
        $data = $this->payload();
        $this->actingAs($session->user)->postJson(route('schedule.execute', $session), $data)->assertCreated();
        $this->postJson(route('schedule.execute', $session), $data)->assertOk()->assertJsonPath('created', false);
        $this->postJson(route('schedule.execute', $session), [...$data, 'actual_minutes' => 45])->assertUnprocessable()->assertJsonValidationErrors('submission_key');
        $this->assertDatabaseCount('study_records', 1);
        $this->assertDatabaseCount('study_record_items', 1);
        $this->assertDatabaseCount('planned_execution_submissions', 1);
    }

    public function test_multiple_executions_recalculate_after_edit_and_removal_without_replaying_deleted_items(): void
    {
        $session = PlannedStudySession::factory()->create(['planned_minutes' => 60]);
        $first = $this->payload(30);
        $second = $this->payload(45);
        $this->actingAs($session->user)->postJson(route('schedule.execute', $session), $first)->assertCreated();
        $this->postJson(route('schedule.execute', $session), $second)->assertCreated();
        $record = $session->user->studyRecords()->firstOrFail();
        $items = $record->items()->get();
        $this->assertSame(75, $record->minutes);
        $this->putJson(route('study-records.update', $record), [
            'study_date' => '2026-10-13', 'studied' => true,
            'items' => [['id' => $items[0]->id, 'minutes' => 15]],
        ])->assertOk();
        $this->assertSame($session->id, $items[0]->fresh()->planned_study_session_id);
        $progress = app(WeeklyScheduleService::class)->week($session->user)['days'][1]['sessions']->first();
        $this->assertSame(15, (int) $progress->real_minutes);
        $this->assertSame(25, $progress->execution_percentage);
        $this->postJson(route('schedule.execute', $session), $second)->assertOk()->assertJsonPath('item_id', null)->assertJsonPath('created', false);
        $this->assertSame(1, $record->items()->count());
    }

    public function test_other_users_cancelled_and_inactive_sessions_cannot_be_executed(): void
    {
        $session = PlannedStudySession::factory()->create();
        $this->actingAs(User::factory()->create())->postJson(route('schedule.execute', $session), $this->payload())->assertForbidden();
        $this->actingAs($session->user)->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertRedirect();
        $this->postJson(route('schedule.execute', $session), $this->payload())->assertForbidden();
        $active = PlannedStudySession::factory()->create(['user_id' => $session->user_id, 'subject_id' => $session->subject_id]);
        $active->subject->update(['active' => false]);
        $this->postJson(route('schedule.execute', $active), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('study_records', 0);
        $this->assertDatabaseCount('planned_execution_submissions', 0);
    }

    public function test_future_invalid_duration_and_forged_links_are_rejected(): void
    {
        $session = PlannedStudySession::factory()->create();
        $this->actingAs($session->user);
        foreach ([0, -1, 1.5, 1441] as $minutes) {
            $this->postJson(route('schedule.execute', $session), [...$this->payload(), 'actual_minutes' => $minutes])->assertUnprocessable();
        }
        $this->postJson(route('schedule.execute', $session), [...$this->payload(), 'study_date' => '2026-10-14'])->assertUnprocessable()->assertJsonValidationErrors('study_date');
        $this->postJson(route('study-records.store'), [
            'study_date' => '2026-10-13', 'studied' => true,
            'items' => [['minutes' => 30, 'planned_study_session_id' => $session->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.planned_study_session_id');
        $this->assertDatabaseCount('study_records', 0);
    }

    public function test_daily_capacity_failure_rolls_back_and_legacy_minutes_are_preserved(): void
    {
        $session = PlannedStudySession::factory()->create();
        $record = StudyRecord::factory()->create(['user_id' => $session->user_id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 1430]);
        $record->items()->delete();
        $this->actingAs($session->user)->postJson(route('schedule.execute', $session), $this->payload(30))->assertUnprocessable();
        $this->assertSame(1430, $record->fresh()->minutes);
        $this->assertSame(0, $record->items()->count());
        $this->assertDatabaseCount('planned_execution_submissions', 0);
        $this->postJson(route('schedule.execute', $session), $this->payload(10))->assertCreated();
        $this->assertSame(1440, $record->fresh()->minutes);
        $this->assertSame(2, $record->items()->count());
        $this->assertSame(1, $record->items()->whereNull('planned_study_session_id')->count());
    }

    public function test_planning_itself_and_cancelled_planning_never_create_real_minutes(): void
    {
        $session = PlannedStudySession::factory()->create(['planned_minutes' => 300]);
        $this->actingAs($session->user)->get(route('schedule.index'))->assertOk()->assertSee('Registrar execucao');
        $this->getJson(route('dashboard.stats'))->assertOk()->assertJsonPath('study_indicators.daily.minutes', 0);
        $this->assertDatabaseCount('study_records', 0);
        $this->postJson(route('schedule.execute', $session), $this->payload(30))->assertCreated();
        $this->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertRedirect();
        $this->getJson(route('dashboard.stats'))->assertOk()->assertJsonPath('study_indicators.daily.minutes', 30);
        $this->assertSame(30, $session->studyRecordItems()->sum('minutes'));
    }

    public function test_not_studied_edit_excludes_linked_execution_and_clears_items(): void
    {
        $session = PlannedStudySession::factory()->create();
        $this->actingAs($session->user)->postJson(route('schedule.execute', $session), $this->payload())->assertCreated();
        $record = $session->user->studyRecords()->firstOrFail();
        $this->putJson(route('study-records.update', $record), ['study_date' => '2026-10-13', 'studied' => false, 'items' => []])->assertOk();
        $progress = app(WeeklyScheduleService::class)->week($session->user)['days'][1]['sessions']->first();
        $this->assertSame(0, (int) $progress->real_minutes);
        $this->assertSame('Planejada', $progress->execution_status);
    }
}
