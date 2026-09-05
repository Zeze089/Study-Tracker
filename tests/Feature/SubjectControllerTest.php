<?php

namespace Tests\Feature;

use App\Models\StudyRecord;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_page_lists_active_and_inactive_subjects(): void
    {
        $user = User::factory()->create();
        Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel', 'active' => true]);
        Subject::factory()->create(['user_id' => $user->id, 'name' => 'PHP', 'active' => false]);

        $response = $this->actingAs($user)->get(route('subjects.index'));

        $response->assertOk()
            ->assertSee('Materias')
            ->assertSee('+ Nova materia')
            ->assertSee('Excluir')
            ->assertSee('Laravel')
            ->assertSee('Ativa')
            ->assertSee('PHP')
            ->assertSee('Inativa');
    }

    public function test_user_can_create_a_subject(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('subjects.store'), [
            'name' => 'Python',
            'color' => '#14b8a6',
            'active' => '1',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('subjects', [
            'user_id' => $user->id,
            'name' => 'Python',
            'color' => '#14b8a6',
            'active' => true,
        ]);
    }

    public function test_user_can_edit_a_subject(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id, 'name' => 'PHP']);

        $response = $this->actingAs($user)->put(route('subjects.update', $subject), [
            'name' => 'PHP Avancado',
            'color' => '#6366f1',
            'active' => '1',
        ]);

        $response->assertRedirect();

        $subject->refresh();

        $this->assertSame('PHP Avancado', $subject->name);
        $this->assertSame('#6366f1', $subject->color);
        $this->assertTrue($subject->active);
    }

    public function test_user_can_deactivate_and_reactivate_a_subject(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id, 'active' => true]);

        $this->actingAs($user)->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'color' => $subject->color,
            'active' => '0',
        ])->assertRedirect();

        $this->assertFalse($subject->refresh()->active);

        $this->actingAs($user)->put(route('subjects.update', $subject), [
            'name' => $subject->name,
            'color' => $subject->color,
            'active' => '1',
        ])->assertRedirect();

        $this->assertTrue($subject->refresh()->active);
    }

    public function test_user_can_delete_a_subject_without_history(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Docker']);

        $response = $this->actingAs($user)->delete(route('subjects.destroy', $subject));

        $response->assertRedirect();

        $this->assertDatabaseMissing('subjects', [
            'id' => $subject->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_subject(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $owner->id, 'name' => 'Docker']);

        $response = $this->actingAs($intruder)->delete(route('subjects.destroy', $subject));

        $response->assertForbidden();

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'Docker',
        ]);
    }

    public function test_subject_with_history_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel']);

        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'studied' => true,
        ]);

        $response = $this->actingAs($user)->delete(route('subjects.destroy', $subject));

        $response->assertRedirect()
            ->assertSessionHas('error', 'Esta materia possui registros vinculados. Desative-a para preservar o historico.');

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'Laravel',
        ]);
    }

    public function test_user_cannot_edit_another_users_subject(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $subject = Subject::factory()->create(['user_id' => $owner->id, 'name' => 'Redes']);

        $response = $this->actingAs($intruder)->put(route('subjects.update', $subject), [
            'name' => 'Redes invadidas',
            'color' => '#ef4444',
            'active' => '0',
        ]);

        $response->assertForbidden();

        $this->assertSame('Redes', $subject->refresh()->name);
        $this->assertTrue($subject->active);
    }

    public function test_duplicate_subject_name_for_the_same_user_is_rejected(): void
    {
        $user = User::factory()->create();
        Subject::factory()->create(['user_id' => $user->id, 'name' => 'Laravel']);

        $response = $this->actingAs($user)->post(route('subjects.store'), [
            'name' => 'Laravel',
            'color' => '#14b8a6',
            'active' => '1',
        ]);

        $response->assertSessionHasErrors(['name' => 'Voce ja possui uma materia com este nome.']);
    }

    public function test_different_users_can_use_the_same_subject_name(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        Subject::factory()->create(['user_id' => $owner->id, 'name' => 'Laravel']);

        $this->actingAs($user)->post(route('subjects.store'), [
            'name' => 'Laravel',
            'color' => '#14b8a6',
            'active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('subjects', [
            'user_id' => $user->id,
            'name' => 'Laravel',
        ]);
    }

    public function test_inactive_subject_stays_linked_to_history(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create([
            'user_id' => $user->id,
            'name' => 'Python',
            'active' => false,
        ]);

        StudyRecord::factory()->create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'study_date' => '2026-09-04',
            'studied' => true,
            'content' => 'Funcoes e estruturas de repeticao',
            'minutes' => 90,
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index', [
            'month' => 9,
            'year' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Python')
            ->assertSee('Funcoes e estruturas de repeticao');
    }

    public function test_inactive_subject_is_not_available_for_new_records(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create([
            'user_id' => $user->id,
            'name' => 'Python',
            'active' => false,
        ]);

        $page = $this->actingAs($user)->get(route('dashboard'));

        $page->assertOk()
            ->assertDontSee('Python');

        $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => now()->toDateString(),
            'studied' => true,
            'subject_id' => $subject->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('subject_id');
    }

    public function test_subject_seeder_is_idempotent_without_reactivating_existing_subjects(): void
    {
        $user = User::factory()->create();
        Subject::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laravel',
            'color' => '#000000',
            'active' => false,
        ]);

        $this->seed(SubjectSeeder::class);
        $this->seed(SubjectSeeder::class);

        $this->assertSame(10, Subject::whereBelongsTo($user)->count());

        $laravel = Subject::whereBelongsTo($user)->where('name', 'Laravel')->first();

        $this->assertNotNull($laravel);
        $this->assertSame('#000000', $laravel->color);
        $this->assertFalse($laravel->active);
        $this->assertDatabaseMissing('subjects', [
            'user_id' => $user->id,
            'name' => 'Python',
        ]);
    }
}
