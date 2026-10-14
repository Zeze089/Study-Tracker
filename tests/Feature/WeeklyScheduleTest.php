<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\PlannedStudySession;
use App\Models\StudyRecord;
use App\Models\Subject;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\WeeklyScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeeklyScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'America/Fortaleza'));
    }

    /** @return array<string, mixed> */
    private function payload(Subject $subject): array
    {
        return [
            'subject_id' => $subject->id, 'content' => 'Rotas e controllers',
            'scheduled_date' => '2026-10-14', 'scheduled_time' => '09:30',
            'priority' => 'high', 'planned_minutes' => 90, 'change_reason' => 'Ajuste de horario',
        ];
    }

    public function test_guest_cannot_manage_planning(): void
    {
        $session = PlannedStudySession::factory()->create();
        $this->get(route('schedule.index'))->assertRedirect(route('login'));
        $this->post(route('schedule.store'), [])->assertRedirect(route('login'));
        $this->get(route('schedule.edit', $session))->assertRedirect(route('login'));
        $this->put(route('schedule.update', $session), [])->assertRedirect(route('login'));
        $this->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertRedirect(route('login'));
    }

    public function test_create_edit_and_cancel_preserve_history_without_changing_execution_or_goals(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 45]);
        Goal::factory()->create(['user_id' => $user->id, 'type' => 'weekly', 'target_days' => 4, 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18']);
        $before = app(DashboardService::class)->stats($user);
        $recordCount = StudyRecord::count();
        $itemCount = DB::table('study_record_items')->count();
        $other = User::factory()->create();
        $this->actingAs($user)->post(route('schedule.store'), [
            ...$this->payload($subject), 'user_id' => $other->id, 'timezone' => 'UTC', 'cancelled_at' => now(), 'studied' => true,
        ])->assertRedirect(route('schedule.index', ['week' => '2026-10-14']))->assertSessionHasNoErrors();
        $session = $user->plannedStudySessions()->firstOrFail();
        $this->assertSame($user->id, $session->user_id);
        $this->assertSame('America/Fortaleza', $session->timezone);
        $this->assertNull($session->cancelled_at);
        $this->get(route('schedule.edit', $session))->assertOk()->assertSee('Editar sessao planejada');
        $this->put(route('schedule.update', $session), [
            ...$this->payload($subject), 'revision' => $session->fresh()->revision(), 'planned_minutes' => 120, 'content' => 'Eloquent',
        ])->assertSessionHasNoErrors();
        $this->assertSame(120, $session->fresh()->planned_minutes);
        $this->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertRedirect();
        $session->refresh();
        $this->assertNotNull($session->cancelled_at);
        $this->assertSame('Eloquent', $session->content);
        $this->assertSame(120, $session->planned_minutes);
        $this->assertDatabaseCount('planned_study_sessions', 1);
        $this->get(route('schedule.index'))->assertOk()->assertSee('Cancelada')->assertSee('Eloquent');
        $this->assertSame($recordCount, StudyRecord::count());
        $this->assertSame($itemCount, DB::table('study_record_items')->count());
        $this->assertSame($before, app(DashboardService::class)->stats($user));
    }

    public function test_planning_without_a_time_creates_no_execution_records(): void
    {
        $subject = Subject::factory()->create();
        $this->actingAs($subject->user)->post(route('schedule.store'), [...$this->payload($subject), 'scheduled_time' => null])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('planned_study_sessions', ['subject_id' => $subject->id, 'scheduled_time' => null]);
        $this->assertDatabaseCount('study_records', 0);
        $this->assertDatabaseCount('study_record_items', 0);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_rejects_invalid_planned_sessions(array $changes, string $field): void
    {
        $subject = Subject::factory()->create();
        $this->actingAs($subject->user)->postJson(route('schedule.store'), array_replace($this->payload($subject), $changes))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('planned_study_sessions', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): array
    {
        return [
            'missing subject' => [['subject_id' => null], 'subject_id'],
            'empty content' => [['content' => '   '], 'content'],
            'long content' => [['content' => str_repeat('x', 256)], 'content'],
            'past date' => [['scheduled_date' => '2026-10-12'], 'scheduled_date'],
            'invalid date' => [['scheduled_date' => '2026-02-30'], 'scheduled_date'],
            'invalid time' => [['scheduled_time' => '25:00'], 'scheduled_time'],
            'wrong priority' => [['priority' => 'urgent'], 'priority'],
            'zero minutes' => [['planned_minutes' => 0], 'planned_minutes'],
            'fraction minutes' => [['planned_minutes' => 1.5], 'planned_minutes'],
            'too many minutes' => [['planned_minutes' => 1441], 'planned_minutes'],
            'missing minutes' => [['planned_minutes' => null], 'planned_minutes'],
        ];
    }

    public function test_subjects_and_sessions_are_completely_isolated_by_user(): void
    {
        $owner = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $owner->id, 'name' => 'Materia privada']);
        $session = PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'content' => 'Conteudo privado', 'scheduled_date' => '2026-10-14']);
        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('schedule.index'))->assertOk()->assertDontSee('Materia privada')->assertDontSee('Conteudo privado');
        $this->get(route('schedule.edit', $session))->assertForbidden();
        $this->put(route('schedule.update', $session), $this->payload($subject))->assertForbidden();
        $this->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertForbidden();
        $this->postJson(route('schedule.store'), $this->payload($subject))->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $this->assertNull($session->fresh()->cancelled_at);
        $this->assertSame('Conteudo privado', $session->fresh()->content);
    }

    public function test_inactive_subjects_cannot_be_newly_selected_but_existing_links_are_preserved(): void
    {
        $subject = Subject::factory()->create();
        $session = PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-14']);
        $subject->update(['active' => false]);
        $this->actingAs($subject->user)->postJson(route('schedule.store'), $this->payload($subject))->assertUnprocessable()->assertJsonValidationErrors('subject_id');
        $this->get(route('schedule.edit', $session))->assertOk()->assertSee('(inativa)');
        $this->put(route('schedule.update', $session), [...$this->payload($subject), 'revision' => $session->fresh()->revision()])->assertSessionHasNoErrors();
        $this->delete(route('subjects.destroy', $subject))->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
        $this->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertRedirect();
        $this->delete(route('subjects.destroy', $subject))->assertSessionHas('error');
        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_cancelled_and_past_sessions_cannot_be_edited_and_cancellation_cannot_be_undone(): void
    {
        $subject = Subject::factory()->create();
        $session = PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-14']);
        $this->actingAs($subject->user)->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertRedirect();
        $cancelledAt = $session->fresh()->cancelled_at->toDateTimeString();
        $this->get(route('schedule.edit', $session))->assertForbidden();
        $this->put(route('schedule.update', $session), $this->payload($subject))->assertForbidden();
        $this->patch(route('schedule.cancel', $session), ['revision' => $session->fresh()->revision()])->assertForbidden();
        $this->assertSame($cancelledAt, $session->fresh()->cancelled_at->toDateTimeString());
        $past = PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-12']);
        $this->get(route('schedule.edit', $past))->assertForbidden();
        $this->patch(route('schedule.cancel', $past), ['revision' => $past->fresh()->revision()])->assertRedirect();
    }

    public function test_stale_edit_cannot_overwrite_a_cancelled_session(): void
    {
        $subject = Subject::factory()->create();
        $session = PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-14']);
        $stale = $session->replicate();
        $stale->id = $session->id;
        app(WeeklyScheduleService::class)->cancel($subject->user, $session);
        $this->expectException(AuthorizationException::class);
        app(WeeklyScheduleService::class)->save($subject->user, $this->payload($subject), $stale);
    }

    public function test_week_navigation_boundaries_ordering_and_cancelled_totals(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id]);
        $base = ['subject_id' => $subject->id, 'scheduled_date' => '2026-12-28'];
        $later = PlannedStudySession::factory()->create([...$base, 'scheduled_time' => '10:00', 'planned_minutes' => 60]);
        $earlier = PlannedStudySession::factory()->create([...$base, 'scheduled_time' => '08:00', 'planned_minutes' => 30]);
        $untimed = PlannedStudySession::factory()->create([...$base, 'scheduled_time' => null, 'planned_minutes' => 30]);
        PlannedStudySession::factory()->create([...$base, 'scheduled_time' => '11:00', 'cancelled_at' => now(), 'planned_minutes' => 200]);
        PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2027-01-03', 'planned_minutes' => 60]);
        PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'scheduled_date' => '2027-01-04']);
        $response = $this->actingAs($user)->get(route('schedule.index', ['week' => '2027-01-01']))->assertOk();
        $week = $response->viewData('schedule');
        $this->assertSame('2026-12-28', $week['starts_on']);
        $this->assertSame('2027-01-03', $week['ends_on']);
        $this->assertSame('2026-12-21', $week['previous_week']);
        $this->assertSame('2027-01-04', $week['next_week']);
        $this->assertCount(7, $week['days']);
        $this->assertSame(4, $week['active_count']);
        $this->assertSame(1, $week['cancelled_count']);
        $this->assertSame('3h', $week['planned_duration']);
        $this->assertSame($earlier->id, $week['days'][0]['sessions'][0]->id);
        $this->assertSame($later->id, $week['days'][0]['sessions'][1]->id);
        $this->assertSame($untimed->id, $week['days'][0]['sessions']->last()->id);
    }

    public function test_week_and_today_validation_follow_local_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 01:00:00', 'UTC'));
        $subject = Subject::factory()->create(['user_id' => User::factory()->create(['timezone' => 'America/Fortaleza'])->id]);
        $this->actingAs($subject->user)->post(route('schedule.store'), [...$this->payload($subject), 'scheduled_date' => '2026-10-18'])->assertSessionHasNoErrors();
        $response = $this->get(route('schedule.index'))->assertOk();
        $this->assertSame('2026-10-12', $response->viewData('schedule')['starts_on']);
        $this->travel(4)->hours();
        $this->assertSame('2026-10-19', app(WeeklyScheduleService::class)->week($subject->user)['starts_on']);
    }

    public function test_nonexistent_dst_time_is_rejected(): void
    {
        $subject = Subject::factory()->create(['user_id' => User::factory()->create(['timezone' => 'America/New_York'])->id]);
        $this->actingAs($subject->user)->postJson(route('schedule.store'), [
            ...$this->payload($subject), 'scheduled_date' => '2027-03-14', 'scheduled_time' => '02:30',
        ])->assertUnprocessable()->assertJsonValidationErrors('scheduled_time');
    }

    public function test_weekly_loading_uses_two_queries_without_n_plus_one(): void
    {
        $subject = Subject::factory()->create();
        PlannedStudySession::factory()->count(20)->create(['subject_id' => $subject->id, 'scheduled_date' => '2026-10-14']);
        $user = $subject->user;
        DB::enableQueryLog();
        DB::flushQueryLog();
        $week = app(WeeklyScheduleService::class)->week($user);
        foreach ($week['days'] as $day) {
            foreach ($day['sessions'] as $session) {
                $this->assertSame($subject->name, $session->subject->name);
            }
        }
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_empty_agenda_escaped_content_and_invalid_week(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('schedule.index'))->assertOk()->assertSee('Sem sessoes.')->assertSee('Cadastrar materia');
        $this->getJson(route('schedule.index', ['week' => '2026-02-30']))->assertUnprocessable()->assertJsonValidationErrors('week');
        $subject = Subject::factory()->create(['user_id' => $user->id]);
        PlannedStudySession::factory()->create(['subject_id' => $subject->id, 'content' => '<script>alert(1)</script>', 'scheduled_date' => '2026-10-14']);
        $this->get(route('schedule.index'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
