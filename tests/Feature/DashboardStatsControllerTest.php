<?php

namespace Tests\Feature;

use App\Models\StudyRecord;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardStatsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_accesses_dashboard_with_their_current_statistics(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel']);

        foreach (['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04'] as $date) {
            StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => $date, 'studied' => true, 'minutes' => 30]);
        }
        foreach (['2026-09-02', '2026-09-03', '2026-09-04'] as $date) {
            StudyRecord::factory()->create(['user_id' => $user->id, 'subject_id' => $subject->id, 'study_date' => $date, 'studied' => true, 'minutes' => 60]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Sequencia atual')
            ->assertSee('Laravel')
            ->assertSee('3h')
            ->assertSee('Minutos estudados nos ultimos 7 dias');

        $this->actingAs($user)->getJson(route('dashboard.stats'))
            ->assertOk()
            ->assertJsonPath('current_streak', 3)
            ->assertJsonPath('longest_streak', 4)
            ->assertJsonPath('month_summary.consistency', 60);
    }

    public function test_dashboard_stats_endpoint_returns_the_authenticated_users_payload(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel']);

        StudyRecord::factory()->create(['user_id' => $user->id, 'subject_id' => $subject->id, 'study_date' => '2026-09-04', 'studied' => true, 'content' => 'Eloquent Relationships', 'minutes' => 150]);
        StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-09-03', 'studied' => false, 'minutes' => null]);

        $response = $this->actingAs($user)->getJson(route('dashboard.stats'));

        $response->assertOk()
            ->assertJsonPath('month_summary.elapsed_days', 5)
            ->assertJsonPath('month_summary.studied_days', 1)
            ->assertJsonPath('month_summary.not_studied_days', 1)
            ->assertJsonPath('month_summary.unregistered_days', 3)
            ->assertJsonPath('month_summary.total_minutes', 150)
            ->assertJsonPath('month_summary.total_hours_label', '2h 30min')
            ->assertJsonPath('month_summary.consistency', 20)
            ->assertJsonPath('top_subject.name', 'Laravel')
            ->assertJsonPath('recent_activity.0.content', 'Eloquent Relationships')
            ->assertJsonPath('recent_chart.5.minutes', 150);
    }

    public function test_dashboard_data_does_not_include_another_users_records(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $secret = Subject::factory()->create(['user_id' => $otherUser->id, 'name' => 'Materia secreta']);

        StudyRecord::factory()->create([
            'user_id' => $otherUser->id,
            'subject_id' => $secret->id,
            'study_date' => '2026-09-04',
            'studied' => true,
            'minutes' => 999,
            'notes' => 'Conteudo privado.',
        ]);

        $response = $this->actingAs($user)->getJson(route('dashboard.stats'));

        $response->assertOk()
            ->assertJsonMissing(['name' => 'Materia secreta'])
            ->assertJsonMissing(['notes' => 'Conteudo privado.'])
            ->assertJsonPath('month_summary.total_minutes', 0)
            ->assertJsonPath('top_subject', null);
    }

    public function test_dashboard_shows_a_valid_empty_state_for_new_user(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Voce ainda nao possui registros de estudo.')
            ->assertSee('0min')
            ->assertSee('Registrar estudo de hoje');

        $this->actingAs($user)->getJson(route('dashboard.stats'))
            ->assertOk()
            ->assertJsonPath('current_streak', 0)
            ->assertJsonPath('longest_streak', 0)
            ->assertJsonPath('month_summary.studied_days', 0)
            ->assertJsonPath('month_summary.total_minutes', 0)
            ->assertJsonPath('month_summary.consistency', 0);
    }

    public function test_dashboard_stats_reflect_a_saved_today_record(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel']);

        $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'subject_id' => $subject->id,
            'content' => 'Funcoes e estruturas de repeticao',
            'hours' => 2,
            'time_minutes' => 30,
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson(route('dashboard.stats'));

        $response->assertOk()
            ->assertJsonPath('today_record.study_date', '2026-09-05')
            ->assertJsonPath('today_record.subject_name', 'Laravel')
            ->assertJsonPath('today_record.content', 'Funcoes e estruturas de repeticao')
            ->assertJsonPath('today_record.duration_label', '2h 30min')
            ->assertJsonPath('month_summary.studied_days', 1)
            ->assertJsonPath('month_summary.total_minutes', 150);
    }
}
