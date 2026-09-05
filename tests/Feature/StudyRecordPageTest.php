<?php

namespace Tests\Feature;

use App\Models\StudyRecord;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudyRecordPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-05 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_displays_the_quick_daily_record_panel_without_a_record(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Registrar estudo de hoje')
            ->assertSee('Nenhum estudo registrado ainda.')
            ->assertSee('Voce estudou?')
            ->assertSee('href="http://study-tracker.test/favicon.svg"', false)
            ->assertSee('aria-label="Study Tracker"', false)
            ->assertSee('study-tracker-theme')
            ->assertSee('themeToggle()', false);
    }

    public function test_dashboard_displays_the_existing_daily_record_summary(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laravel',
            'color' => '#ef4444',
        ]);

        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'study_date' => '2026-09-05',
            'studied' => true,
            'content' => 'Middleware e autenticacao',
            'minutes' => 150,
            'notes' => 'Estudei autenticacao.',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Editar registro')
            ->assertSee('Laravel')
            ->assertSee('Middleware e autenticacao')
            ->assertSee('2h 30min')
            ->assertSee('Estudei autenticacao.');
    }

    public function test_calendar_page_reuses_the_daily_record_modal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('calendar.index'));

        $response->assertOk()
            ->assertSee('Calendario mensal')
            ->assertSee('Resumo do mes')
            ->assertSee('Voce estudou?')
            ->assertSee('data-date="2026-09-05"', false);
    }
}
