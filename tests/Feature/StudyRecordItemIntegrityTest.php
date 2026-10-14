<?php

namespace Tests\Feature;

use App\Http\Requests\StoreStudyRecordRequest;
use App\Models\StudyRecord;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\StudyRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudyRecordItemIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00'));
    }

    public function test_existing_ids_are_updated_new_items_created_and_only_omitted_items_removed(): void
    {
        $user = User::factory()->create();
        $record = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $kept = $record->items()->firstOrFail();
        $removed = $record->items()->create(['content' => 'Remover', 'minutes' => 20, 'position' => 2]);
        $other = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-12', 'studied' => true, 'minutes' => 40]);
        $untouched = $other->items()->firstOrFail();
        $this->actingAs($user)->putJson(route('study-records.update', $record), [
            'study_date' => '2026-10-13', 'studied' => true,
            'items' => [['id' => $kept->id, 'content' => 'Atualizado', 'minutes' => 60], ['id' => null, 'content' => 'Novo', 'minutes' => 15]],
        ])->assertOk()->assertJsonPath('record.items.0.id', $kept->id)->assertJsonPath('record.minutes', 75);
        $this->assertDatabaseHas('study_record_items', ['id' => $kept->id, 'content' => 'Atualizado', 'minutes' => 60]);
        $this->assertDatabaseMissing('study_record_items', ['id' => $removed->id]);
        $this->assertDatabaseHas('study_record_items', ['id' => $untouched->id, 'study_record_id' => $other->id]);
        $this->assertSame(2, $record->items()->count());
    }

    public function test_foreign_ids_are_rejected_even_in_blank_items_or_a_not_studied_payload(): void
    {
        $user = User::factory()->create();
        $record = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $foreign = StudyRecord::factory()->create(['study_date' => '2026-10-13', 'studied' => true, 'minutes' => 90])->items()->firstOrFail();
        foreach ([true, false] as $studied) {
            $this->actingAs($user)->putJson(route('study-records.update', $record), [
                'study_date' => '2026-10-13', 'studied' => $studied, 'items' => [['id' => $foreign->id]],
            ])->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
        }
        $this->assertSame(30, $record->fresh()->minutes);
        $this->assertSame(90, $foreign->fresh()->minutes);
        $this->assertSame(1, $record->items()->count());
    }

    public function test_items_from_another_record_of_the_same_user_cannot_be_moved(): void
    {
        $user = User::factory()->create();
        $record = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $other = StudyRecord::factory()->create(['user_id' => $user->id, 'study_date' => '2026-10-12', 'studied' => true, 'minutes' => 60]);
        $item = $other->items()->firstOrFail();
        $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-10-13', 'studied' => true, 'items' => [['id' => $item->id, 'minutes' => 99]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
        $this->assertSame($other->id, $item->fresh()->study_record_id);
        $this->assertSame(30, $record->fresh()->minutes);
    }

    public function test_duplicate_and_missing_ids_reject_the_whole_update(): void
    {
        $record = StudyRecord::factory()->create(['study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $item = $record->items()->firstOrFail();
        $this->actingAs($record->user)->putJson(route('study-records.update', $record), [
            'study_date' => '2026-10-13', 'studied' => true,
            'items' => [['id' => $item->id, 'minutes' => 60], ['id' => $item->id, 'minutes' => 20]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.id');
        $this->putJson(route('study-records.update', $record), [
            'study_date' => '2026-10-13', 'studied' => true,
            'items' => [['id' => $item->id, 'minutes' => 60], ['id' => 999999, 'minutes' => 20]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.1.id');
        $this->assertSame(30, $item->fresh()->minutes);
        $this->assertSame(30, $record->fresh()->minutes);
    }

    public function test_stale_item_versions_reject_writes_without_deleting_newer_items(): void
    {
        $record = StudyRecord::factory()->create(['study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $item = $record->items()->firstOrFail();
        $this->actingAs($record->user);
        $snapshot = $this->getJson(route('dashboard.stats'))->json('today_record');
        $payload = [
            'study_date' => '2026-10-13', 'studied' => true, 'items_version' => $snapshot['items_version'],
            'items' => [['id' => $item->id, 'minutes' => 60]],
        ];
        $this->putJson(route('study-records.update', $record), $payload)->assertOk();
        $this->putJson(route('study-records.update', $record), [...$payload, 'notes' => 'Stale'])
            ->assertUnprocessable()->assertJsonValidationErrors('items_version');
        $this->assertSame(60, $item->fresh()->minutes);
        $this->assertNotSame('Stale', $record->fresh()->notes);
    }

    public function test_legacy_single_item_updates_preserve_the_original_id(): void
    {
        $record = StudyRecord::factory()->create(['study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $item = $record->items()->firstOrFail();
        $this->actingAs($record->user)->postJson(route('study-records.store'), [
            'study_date' => '2026-10-13', 'studied' => true, 'content' => 'Legado', 'minutes' => 45,
        ])->assertOk()->assertJsonPath('record.items.0.id', $item->id);
        $this->assertSame(45, $item->fresh()->minutes);
    }

    public function test_identified_roundtrip_preserves_statistics_and_created_at(): void
    {
        $record = StudyRecord::factory()->create(['study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $user = $record->user;
        $item = $record->items()->firstOrFail();
        $created = $item->created_at->toDateTimeString();
        $before = app(DashboardService::class)->stats($user);
        $this->actingAs($user)->putJson(route('study-records.update', $record), [
            'study_date' => '2026-10-13', 'studied' => true,
            'items' => [['id' => $item->id, 'subject_id' => $item->subject_id, 'content' => $item->content, 'minutes' => $item->minutes]],
            'notes' => $record->notes,
        ])->assertOk();
        $this->assertSame($created, $item->fresh()->created_at->toDateTimeString());
        $this->assertSame($before, app(DashboardService::class)->stats($user));
    }

    public function test_not_studied_clears_only_the_authorized_records_items(): void
    {
        $record = StudyRecord::factory()->create(['study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $other = StudyRecord::factory()->create(['study_date' => '2026-10-13', 'studied' => true, 'minutes' => 60]);
        $this->actingAs($record->user)->putJson(route('study-records.update', $record), [
            'study_date' => '2026-10-13', 'studied' => false, 'items' => [],
        ])->assertOk();
        $this->assertSame(0, $record->items()->count());
        $this->assertSame(1, $other->items()->count());
        $this->assertNull($record->fresh()->minutes);
    }

    public function test_item_deleted_after_validation_is_rejected_and_record_update_rolls_back(): void
    {
        $record = StudyRecord::factory()->create(['study_date' => '2026-10-13', 'studied' => true, 'minutes' => 30]);
        $item = $record->items()->firstOrFail();
        $request = StoreStudyRecordRequest::create('/study-records', 'POST', [
            'study_date' => '2026-10-13', 'studied' => true, 'items' => [['id' => $item->id, 'minutes' => 60]],
        ]);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $record->user);
        $request->validateResolved();
        $item->delete();
        try {
            app(StudyRecordService::class)->upsertForUser($record->user, $request);
            $this->fail('An item removed after validation must reject the write.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.id', $exception->errors());
        }
        $this->assertSame(30, $record->fresh()->minutes);
        $this->assertSame(0, $record->items()->count());
    }

    public function test_malformed_items_are_rejected_and_null_items_remain_legacy_compatible(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('study-records.store'), [
            'study_date' => '2026-10-13', 'studied' => true, 'items' => ['bad'],
        ])->assertUnprocessable();
        $this->postJson(route('study-records.store'), [
            'study_date' => '2026-10-13', 'studied' => true, 'items' => ['named' => ['minutes' => 30]],
        ])->assertUnprocessable();
        $this->postJson(route('study-records.store'), [
            'study_date' => '2026-10-13', 'studied' => true, 'items' => null, 'minutes' => 30,
        ])->assertCreated()->assertJsonPath('record.minutes', 30);
    }
}
