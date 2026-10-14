<?php

namespace App\Services;

use App\Http\Requests\StoreStudyRecordRequest;
use App\Models\StudyRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StudyRecordService
{
    public function __construct(private StudyRecordFormatter $formatter) {}

    public function upsertForUser(User $user, StoreStudyRecordRequest $request): StudyRecord
    {
        return DB::transaction(function () use ($request, $user): StudyRecord {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $payload = $this->payloadFromRequest($request);
            $items = $request->boolean('studied') ? $request->studyItems() : [];
            $studyRecord = $user->studyRecords()
                ->whereDate('study_date', $payload['study_date'])
                ->lockForUpdate()
                ->first();

            $this->assertItemsVersion($studyRecord, $request);

            if ($studyRecord) {
                Gate::forUser($user)->authorize('update', $studyRecord);
                $studyRecord->update($payload);
                $this->syncItems($studyRecord, $items, ! is_array($request->input('items')));

                return $studyRecord->load(['subject:id,name,color', 'items.subject:id,name,color']);
            }

            $studyRecord = StudyRecord::create($payload);
            $this->syncItems($studyRecord, $items, ! is_array($request->input('items')));

            return $studyRecord->load(['subject:id,name,color', 'items.subject:id,name,color']);
        }, 3);
    }

    public function update(StudyRecord $studyRecord, StoreStudyRecordRequest $request): StudyRecord
    {
        return DB::transaction(function () use ($request, $studyRecord): StudyRecord {
            $user = $request->user();
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $studyRecord = $user->studyRecords()->whereKey($studyRecord->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('update', $studyRecord);
            $this->assertItemsVersion($studyRecord, $request);
            $conflict = $user->studyRecords()->whereKeyNot($studyRecord->id)
                ->whereDate('study_date', $request->validated('study_date'))->lockForUpdate()->first();
            if ($conflict !== null) {
                throw ValidationException::withMessages(['study_date' => 'Ja existe um registro para esta data.']);
            }
            $items = $request->boolean('studied') ? $request->studyItems() : [];

            $studyRecord->update($this->payloadFromRequest($request));
            $this->syncItems($studyRecord, $items, ! is_array($request->input('items')));

            return $studyRecord->load(['subject:id,name,color', 'items.subject:id,name,color']);
        }, 3);
    }

    private function payloadFromRequest(StoreStudyRecordRequest $request): array
    {
        $validated = $request->validated();
        $studied = (bool) $validated['studied'];
        $items = $studied ? $request->studyItems() : [];
        $firstItem = $items[0] ?? null;

        return [
            'user_id' => $request->user()->id,
            'subject_id' => $firstItem['subject_id'] ?? null,
            'study_date' => $validated['study_date'],
            'studied' => $studied,
            'content' => $firstItem['content'] ?? null,
            'minutes' => $studied ? $request->totalStudyMinutes() : null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * @param  array<int, array{id: int|null, subject_id: int|null, content: string|null, minutes: int|null, position: int}>  $items
     */
    private function syncItems(StudyRecord $studyRecord, array $items, bool $legacy = false): void
    {
        $existing = $studyRecord->items()->lockForUpdate()->get()->keyBy('id');
        if ($legacy && count($items) === 1 && $existing->isNotEmpty()) {
            $items[0]['id'] = $existing->first()->id;
        }
        $submitted = [];
        foreach ($items as $index => $item) {
            $id = $item['id'] ?? null;
            if ($id !== null && (! $existing->has($id) || in_array($id, $submitted, true))) {
                throw ValidationException::withMessages(["items.$index.id" => 'O item nao pertence a este registro ou foi alterado.']);
            }
            if ($id !== null) {
                $submitted[] = $id;
            }
        }
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            unset($item['id']);
            if ($id === null) {
                $studyRecord->items()->create($item);
            } else {
                $existing->get($id)->update($item);
            }
        }
        $removed = $existing->keys()->diff($submitted)->all();
        if ($removed !== []) {
            $studyRecord->items()->whereIn('id', $removed)->delete();
        }
    }

    private function assertItemsVersion(?StudyRecord $record, StoreStudyRecordRequest $request): void
    {
        if (! $request->filled('items_version')) {
            return;
        }
        if ($record !== null) {
            $record->setRelation('items', $record->items()->lockForUpdate()->get());
        }
        if ($record === null || ! hash_equals($this->formatter->itemsVersion($record), $request->validated('items_version'))) {
            throw ValidationException::withMessages(['items_version' => 'Os itens foram alterados. Reabra o registro antes de salvar.']);
        }
    }
}
