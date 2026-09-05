<?php

namespace Tests\Feature;

use App\Models\StudyRecord;
use App\Models\StudyRecordItem;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudyRecordControllerTest extends TestCase
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

    public function test_user_can_register_that_they_studied_with_only_the_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('record.studied', true)
            ->assertJsonPath('record.subject_id', null)
            ->assertJsonPath('record.content', null)
            ->assertJsonPath('record.minutes', null)
            ->assertJsonPath('record.notes', null);

        $studyRecord = StudyRecord::whereBelongsTo($user)->whereDate('study_date', '2026-09-05')->first();

        $this->assertNotNull($studyRecord);
        $this->assertTrue($studyRecord->studied);
        $this->assertNull($studyRecord->subject_id);
        $this->assertNull($studyRecord->content);
        $this->assertNull($studyRecord->minutes);
        $this->assertNull($studyRecord->notes);
    }

    public function test_user_can_register_that_they_did_not_study(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => false,
        ]);

        $response->assertCreated()
            ->assertJsonPath('record.studied', false)
            ->assertJsonPath('record.content', null);

        $studyRecord = StudyRecord::whereBelongsTo($user)->whereDate('study_date', '2026-09-05')->first();

        $this->assertNotNull($studyRecord);
        $this->assertFalse($studyRecord->studied);
    }

    public function test_user_can_register_a_past_day(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-04',
            'studied' => true,
        ]);

        $response->assertCreated();

        $studyRecord = StudyRecord::whereBelongsTo($user)->whereDate('study_date', '2026-09-04')->first();

        $this->assertNotNull($studyRecord);
        $this->assertTrue($studyRecord->studied);
    }

    public function test_user_cannot_register_a_future_day(): void
    {
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

    public function test_user_cannot_use_another_users_subject(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherSubject = Subject::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'subject_id' => $otherSubject->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('subject_id');
    }

    public function test_user_can_register_multiple_subject_items_for_the_same_day(): void
    {
        $user = User::factory()->create();
        $laravel = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel']);
        $database = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Banco de Dados']);

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'items' => [
                [
                    'subject_id' => $laravel->id,
                    'content' => 'Rotas e controllers',
                    'hours' => 1,
                    'time_minutes' => 20,
                ],
                [
                    'subject_id' => $database->id,
                    'content' => 'Relacionamentos',
                    'hours' => 0,
                    'time_minutes' => 40,
                ],
            ],
            'notes' => 'Dia dividido em duas materias.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('record.minutes', 120)
            ->assertJsonPath('record.duration_label', '2h')
            ->assertJsonPath('record.subjects_label', 'Laravel + 1 materia')
            ->assertJsonPath('record.content_label', 'Rotas e controllers; Relacionamentos')
            ->assertJsonPath('record.items.0.subject_name', 'Laravel')
            ->assertJsonPath('record.items.0.minutes', 80)
            ->assertJsonPath('record.items.1.subject_name', 'Banco de Dados')
            ->assertJsonPath('record.items.1.minutes', 40);

        $studyRecord = StudyRecord::whereBelongsTo($user)->whereDate('study_date', '2026-09-05')->first();

        $this->assertNotNull($studyRecord);
        $this->assertSame(120, $studyRecord->minutes);
        $this->assertSame(2, StudyRecordItem::whereBelongsTo($studyRecord)->count());
    }

    public function test_user_cannot_use_another_users_subject_inside_items(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherSubject = Subject::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'items' => [
                [
                    'subject_id' => $otherSubject->id,
                    'content' => 'Conteudo privado',
                    'hours' => 1,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.subject_id');

        $this->assertDatabaseMissing('study_records', [
            'user_id' => $user->id,
            'study_date' => '2026-09-05',
        ]);
    }

    public function test_total_time_across_items_cannot_exceed_twenty_four_hours(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'items' => [
                ['hours' => 20],
                ['hours' => 5],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('hours');
    }

    public function test_user_cannot_update_another_users_study_record(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $studyRecord = StudyRecord::factory()->create([
            'user_id' => $owner->id,
            'study_date' => '2026-09-05',
            'studied' => true,
            'minutes' => 60,
        ]);

        $response = $this->actingAs($intruder)->putJson(route('study-records.update', $studyRecord), [
            'study_date' => '2026-09-05',
            'studied' => false,
        ]);

        $response->assertForbidden();

        $this->assertTrue($studyRecord->refresh()->studied);
        $this->assertSame(60, $studyRecord->minutes);
    }

    public function test_registering_the_same_date_updates_the_existing_record(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id]);

        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'study_date' => '2026-09-05',
            'studied' => false,
            'minutes' => null,
        ]);

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'subject_id' => $subject->id,
            'content' => 'Middleware e autenticacao',
            'hours' => 1,
            'time_minutes' => 20,
            'notes' => 'Estudei middleware.',
        ]);

        $response->assertOk()
            ->assertJsonPath('record.minutes', 80);

        $this->assertSame(1, StudyRecord::whereBelongsTo($user)->whereDate('study_date', '2026-09-05')->count());
        $studyRecord = StudyRecord::whereBelongsTo($user)->whereDate('study_date', '2026-09-05')->first();

        $this->assertTrue($studyRecord->studied);
        $this->assertSame($subject->id, $studyRecord->subject_id);
        $this->assertSame('Middleware e autenticacao', $studyRecord->content);
        $this->assertSame(80, $studyRecord->minutes);
        $this->assertSame('Estudei middleware.', $studyRecord->notes);
    }

    public function test_record_accepts_content(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'content' => 'Eloquent Relationships',
        ]);

        $response->assertCreated()
            ->assertJsonPath('record.content', 'Eloquent Relationships');

        $studyRecord = StudyRecord::whereBelongsTo($user)->whereDate('study_date', '2026-09-05')->first();

        $this->assertNotNull($studyRecord);
        $this->assertSame('Eloquent Relationships', $studyRecord->content);
    }

    public function test_record_content_can_be_updated(): void
    {
        $user = User::factory()->create();
        $studyRecord = StudyRecord::factory()->create([
            'user_id' => $user->id,
            'study_date' => '2026-09-05',
            'studied' => true,
            'content' => 'Rotas',
        ]);

        $response = $this->actingAs($user)->putJson(route('study-records.update', $studyRecord), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'content' => 'Rotas e controllers',
        ]);

        $response->assertOk()
            ->assertJsonPath('record.content', 'Rotas e controllers');

        $this->assertSame('Rotas e controllers', $studyRecord->refresh()->content);
    }

    public function test_hours_and_minutes_are_stored_as_total_minutes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-09-05',
            'studied' => true,
            'hours' => 2,
            'time_minutes' => 30,
        ]);

        $response->assertCreated()
            ->assertJsonPath('record.minutes', 150)
            ->assertJsonPath('record.duration_label', '2h 30min');

        $studyRecord = StudyRecord::whereBelongsTo($user)->whereDate('study_date', '2026-09-05')->first();

        $this->assertNotNull($studyRecord);
        $this->assertSame(150, $studyRecord->minutes);
    }
}
