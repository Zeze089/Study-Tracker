<?php

namespace Tests\Feature;

use App\Models\StudyRecord;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_opens_the_current_month_by_default(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index'));

        $response->assertOk()
            ->assertSee('Setembro 2026')
            ->assertSee('Seg')
            ->assertSee('Dom')
            ->assertSee('Mes atual')
            ->assertSee('data-date="2026-09-05"', false);
    }

    public function test_opens_a_past_month_from_query_parameters(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index', [
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Agosto 2026')
            ->assertSee('Setembro &gt;', false);
    }

    public function test_opens_another_year_from_query_parameters(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index', [
            'month' => 1,
            'year' => 2027,
        ]));

        $response->assertOk()
            ->assertSee('Janeiro 2027')
            ->assertSee('&lt; Dezembro', false)
            ->assertSee('Fevereiro &gt;', false);
    }

    public function test_invalid_query_parameters_fall_back_to_the_current_month(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index', [
            'month' => 15,
            'year' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Setembro 2026')
            ->assertDontSee('2026-15');
    }

    public function test_displays_studied_not_studied_and_unregistered_days(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();
        $subject = Subject::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laravel',
            'color' => '#14b8a6',
        ]);

        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'study_date' => '2026-09-04',
            'studied' => true,
            'content' => 'Middleware e autenticacao',
            'minutes' => 150,
        ]);
        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'study_date' => '2026-09-03',
            'studied' => false,
            'minutes' => null,
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index'));

        $response->assertOk()
            ->assertSee('Laravel')
            ->assertSee('Middleware e autenticacao')
            ->assertSee('2h 30min')
            ->assertSee('Estudou')
            ->assertSee('Nao estudou')
            ->assertSee('Nao registrado');
    }

    public function test_calendar_does_not_show_another_users_records(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherSubject = Subject::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Materia secreta',
        ]);

        StudyRecord::factory()->create([
            'user_id' => $otherUser->id,
            'subject_id' => $otherSubject->id,
            'study_date' => '2026-09-04',
            'studied' => true,
            'minutes' => 180,
            'notes' => 'Conteudo privado de outro usuario.',
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index'));

        $response->assertOk()
            ->assertDontSee('Materia secreta')
            ->assertDontSee('Conteudo privado de outro usuario.');
    }

    public function test_future_day_is_visible_but_not_clickable_in_the_ui(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index'));

        $response->assertOk()
            ->assertSee('data-date="2026-09-06"', false)
            ->assertSee('Ainda nao e possivel registrar esta data.')
            ->assertSee('disabled', false);
    }

    public function test_future_date_still_cannot_be_registered_by_the_backend(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-06',
            'studied' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('study_date');

        $this->assertDatabaseMissing('study_records', [
            'user_id' => $user->id,
            'study_date' => '2026-09-06',
        ]);
    }

    public function test_saving_record_returns_the_payload_used_to_update_calendar_day(): void
    {
        $this->travelTo(Carbon::parse('2026-09-05 12:00:00'));
        $user = User::factory()->create();
        $subject = Subject::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laravel',
        ]);

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-04',
            'studied' => true,
            'subject_id' => $subject->id,
            'content' => 'Rotas e controllers',
            'hours' => 2,
            'time_minutes' => 30,
            'notes' => 'Rotas e controllers.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('record.study_date', '2026-09-04')
            ->assertJsonPath('record.studied', true)
            ->assertJsonPath('record.subject_name', 'Laravel')
            ->assertJsonPath('record.content', 'Rotas e controllers')
            ->assertJsonPath('record.duration_label', '2h 30min')
            ->assertJsonPath('record.notes', 'Rotas e controllers.');
    }
}
