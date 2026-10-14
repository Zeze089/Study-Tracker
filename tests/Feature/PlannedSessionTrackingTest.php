<?php

namespace Tests\Feature;

use App\Models\PlannedStudySession;
use App\Models\StudyRecord;
use App\Models\StudyRecordItem;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\WeeklyScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlannedSessionTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'America/Fortaleza'));
    }

    #[DataProvider('states')]
    public function test_automatic_states_and_overdue_indicators(int $real, bool $cancelled, string $date, ?string $time, string $status, bool $overdue): void
    {
        $session = PlannedStudySession::factory()->create([
            'planned_minutes' => 60, 'scheduled_date' => $date, 'scheduled_time' => $time,
            'cancelled_at' => $cancelled ? now() : null,
        ]);
        if ($real > 0) {
            $record = StudyRecord::factory()->create(['user_id' => $session->user_id, 'study_date' => '2026-10-14', 'studied' => true, 'minutes' => null, 'content' => null]);
            StudyRecordItem::factory()->create(['study_record_id' => $record->id, 'subject_id' => $session->subject_id, 'planned_study_session_id' => $session->id, 'minutes' => $real]);
        }
        $week = app(WeeklyScheduleService::class)->week($session->user);
        $tracked = collect($week['days'])->flatMap(fn ($day) => $day['sessions'])->first();
        $this->assertSame($status, $tracked->execution_status);
        $this->assertSame($overdue, $tracked->is_overdue);
        $this->assertSame($overdue ? 1 : 0, $week['overdue_count']);
    }

    /** @return array<string, array{int, bool, string, string|null, string, bool}> */
    public static function states(): array
    {
        return [
            'planned future' => [0, false, '2026-10-15', null, 'Planejada', false],
            'today without time' => [0, false, '2026-10-14', null, 'Planejada', false],
            'past day' => [0, false, '2026-10-12', null, 'Planejada', true],
            'past hour' => [0, false, '2026-10-14', '09:00', 'Planejada', true],
            'partial overdue' => [30, false, '2026-10-12', null, 'Parcial', true],
            'completed' => [60, false, '2026-10-12', null, 'Concluida', false],
            'cancelled' => [30, true, '2026-10-12', null, 'Cancelada', false],
        ];
    }

    public function test_rescheduling_overdue_partial_session_preserves_execution_and_statistics(): void
    {
        $session = PlannedStudySession::factory()->create(['scheduled_date' => '2026-10-12', 'planned_minutes' => 90]);
        $user = $session->user;
        $this->actingAs($user)->postJson(route('schedule.execute', $session), [
            'study_date' => '2026-10-14', 'actual_minutes' => 30, 'submission_key' => (string) Str::uuid(),
        ])->assertCreated();
        $item = $session->studyRecordItems()->firstOrFail();
        $before = app(DashboardService::class)->stats($user);
        $revision = $session->fresh()->revision();
        $this->patch(route('schedule.reschedule', $session), [
            'scheduled_date' => '2026-10-16', 'scheduled_time' => '10:00', 'reason' => 'Compromisso pessoal', 'revision' => $revision,
        ])->assertRedirect(route('schedule.index', ['week' => '2026-10-16']));
        $this->assertSame('2026-10-16', $session->fresh()->scheduled_date->toDateString());
        $this->assertSame($session->id, $item->fresh()->planned_study_session_id);
        $this->assertSame(30, $item->fresh()->minutes);
        $after = app(DashboardService::class)->stats($user);
        unset($before['planning'], $after['planning']);
        $this->assertSame($before, $after);
        $change = $session->changes()->firstOrFail();
        $this->assertSame('rescheduled', $change->action);
        $this->assertSame('2026-10-12', $change->before['scheduled_date']);
        $this->assertSame('2026-10-16', $change->after['scheduled_date']);
        $this->assertSame('Compromisso pessoal', $change->reason);
        $this->assertSame($user->id, $change->actor_id);
        $this->assertSame($user->name, $change->actor_name);
        $this->get(route('schedule.index'))->assertOk()->assertSee('Historico de mudancas')->assertSee('Compromisso pessoal');
    }

    public function test_old_revision_cannot_reschedule_or_cancel_and_has_no_audit_side_effects(): void
    {
        $session = PlannedStudySession::factory()->create();
        $revision = $session->revision();
        $payload = ['scheduled_date' => '2026-10-15', 'scheduled_time' => null, 'reason' => 'Ajuste', 'revision' => $revision];
        $this->actingAs($session->user)->patchJson(route('schedule.reschedule', $session), $payload)->assertRedirect();
        $this->patchJson(route('schedule.reschedule', $session), [...$payload, 'scheduled_date' => '2026-10-16'])
            ->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->patchJson(route('schedule.cancel', $session), ['revision' => $revision])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame('2026-10-15', $session->fresh()->scheduled_date->toDateString());
        $this->assertNull($session->fresh()->cancelled_at);
        $this->assertSame(1, $session->changes()->count());
    }

    public function test_reschedule_authorization_reason_dates_and_cancelled_state_are_enforced(): void
    {
        $session = PlannedStudySession::factory()->create();
        $payload = ['scheduled_date' => '2026-10-15', 'reason' => 'Ajuste', 'revision' => $session->revision()];
        $this->actingAs(User::factory()->create())->patchJson(route('schedule.reschedule', $session), $payload)->assertForbidden();
        $this->actingAs($session->user)->patchJson(route('schedule.reschedule', $session), [...$payload, 'reason' => ' '])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->patchJson(route('schedule.reschedule', $session), [...$payload, 'scheduled_date' => '2026-10-13'])->assertUnprocessable()->assertJsonValidationErrors('scheduled_date');
        $this->patchJson(route('schedule.reschedule', $session), [...$payload, 'revision' => null])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertRedirect();
        $this->patchJson(route('schedule.reschedule', $session), $payload)->assertForbidden();
        $this->assertSame(1, $session->changes()->count());
    }

    public function test_no_time_session_only_becomes_overdue_after_local_day_ends(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 01:00:00', 'UTC'));
        $session = PlannedStudySession::factory()->create(['scheduled_date' => '2026-10-14', 'scheduled_time' => null, 'timezone' => 'America/Fortaleza']);
        $service = app(WeeklyScheduleService::class);
        $week = $service->week($session->user);
        $this->assertSame(0, $week['overdue_count']);
        $this->travel(4)->hours();
        $week = $service->week($session->user);
        $this->assertSame(1, $week['overdue_count']);
    }

    public function test_reschedule_rejects_nonexistent_dst_time(): void
    {
        $session = PlannedStudySession::factory()->create(['timezone' => 'America/New_York']);
        $this->actingAs($session->user)->patchJson(route('schedule.reschedule', $session), [
            'scheduled_date' => '2027-03-14', 'scheduled_time' => '02:30', 'reason' => 'Ajuste', 'revision' => $session->revision(),
        ])->assertUnprocessable()->assertJsonValidationErrors('scheduled_time');
    }
}
