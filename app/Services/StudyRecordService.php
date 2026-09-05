<?php

namespace App\Services;

use App\Http\Requests\StoreStudyRecordRequest;
use App\Models\StudyRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StudyRecordService
{
    public function upsertForUser(User $user, StoreStudyRecordRequest $request): StudyRecord
    {
        return DB::transaction(function () use ($request, $user): StudyRecord {
            $payload = $this->payloadFromRequest($request);
            $items = $request->boolean('studied') ? $request->studyItems() : [];
            $studyRecord = $user->studyRecords()
                ->whereDate('study_date', $payload['study_date'])
                ->first();

            if ($studyRecord) {
                $studyRecord->update($payload);
                $this->syncItems($studyRecord, $items);

                return $studyRecord->load(['subject:id,name,color', 'items.subject:id,name,color']);
            }

            $studyRecord = StudyRecord::create($payload);
            $this->syncItems($studyRecord, $items);

            return $studyRecord->load(['subject:id,name,color', 'items.subject:id,name,color']);
        });
    }

    public function update(StudyRecord $studyRecord, StoreStudyRecordRequest $request): StudyRecord
    {
        return DB::transaction(function () use ($request, $studyRecord): StudyRecord {
            $items = $request->boolean('studied') ? $request->studyItems() : [];

            $studyRecord->update($this->payloadFromRequest($request));
            $this->syncItems($studyRecord, $items);

            return $studyRecord->load(['subject:id,name,color', 'items.subject:id,name,color']);
        });
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
     * @param  array<int, array{subject_id: int|null, content: string|null, minutes: int|null, position: int}>  $items
     */
    private function syncItems(StudyRecord $studyRecord, array $items): void
    {
        $studyRecord->items()->delete();

        if ($items === []) {
            return;
        }

        $studyRecord->items()->createMany($items);
    }
}
