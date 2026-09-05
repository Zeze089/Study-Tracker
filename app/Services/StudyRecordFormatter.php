<?php

namespace App\Services;

use App\Models\StudyRecord;
use App\Models\StudyRecordItem;
use Illuminate\Support\Collection;

class StudyRecordFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(StudyRecord $studyRecord): array
    {
        $studyRecord->loadMissing('items.subject:id,name,color');

        $items = $studyRecord->items;

        if ($items->isEmpty() && $studyRecord->subject_id !== null) {
            $studyRecord->loadMissing('subject:id,name,color');
        }

        $firstItem = $items->first();
        $totalMinutes = $this->totalMinutes($studyRecord, $items);

        return [
            'id' => $studyRecord->id,
            'study_date' => $studyRecord->study_date->toDateString(),
            'studied' => $studyRecord->studied,
            'subject_id' => $firstItem?->subject_id ?? $studyRecord->subject_id,
            'subject_name' => $firstItem?->subject?->name ?? $studyRecord->subject?->name,
            'subject_color' => $firstItem?->subject?->color ?? $studyRecord->subject?->color,
            'subjects_label' => $this->subjectsLabel($studyRecord, $items),
            'content' => $firstItem?->content ?? $studyRecord->content,
            'content_label' => $this->contentLabel($studyRecord, $items),
            'minutes' => $totalMinutes,
            'hours' => $totalMinutes === null ? null : intdiv($totalMinutes, 60),
            'time_minutes' => $totalMinutes === null ? null : $totalMinutes % 60,
            'notes' => $studyRecord->notes,
            'status_label' => $studyRecord->studied ? 'Estudou' : 'Nao estudou',
            'duration_label' => $this->durationLabel($totalMinutes),
            'items' => $items
                ->map(fn (StudyRecordItem $item): array => $this->serializeItem($item))
                ->values()
                ->all(),
        ];
    }

    public function durationLabel(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return $remainingMinutes.'min';
        }

        if ($remainingMinutes === 0) {
            return $hours.'h';
        }

        return $hours.'h '.$remainingMinutes.'min';
    }

    public function durationLabelOrZero(?int $minutes): string
    {
        return $this->durationLabel($minutes) ?? '0min';
    }

    /**
     * @param  Collection<int, StudyRecordItem>  $items
     */
    private function totalMinutes(StudyRecord $studyRecord, Collection $items): ?int
    {
        if ($items->isEmpty()) {
            return $studyRecord->minutes;
        }

        $minutes = $items
            ->pluck('minutes')
            ->filter(fn (?int $minutes): bool => $minutes !== null);

        return $minutes->isEmpty() ? null : (int) $minutes->sum();
    }

    /**
     * @param  Collection<int, StudyRecordItem>  $items
     */
    private function subjectsLabel(StudyRecord $studyRecord, Collection $items): ?string
    {
        if ($items->isEmpty()) {
            return $studyRecord->subject?->name;
        }

        $names = $items
            ->map(fn (StudyRecordItem $item): string => $item->subject?->name ?? 'Sem materia')
            ->values();

        if ($names->count() === 1) {
            return $names->first();
        }

        return $names->first().' + '.($names->count() - 1).' materia'.($names->count() > 2 ? 's' : '');
    }

    /**
     * @param  Collection<int, StudyRecordItem>  $items
     */
    private function contentLabel(StudyRecord $studyRecord, Collection $items): ?string
    {
        if ($items->isEmpty()) {
            return $studyRecord->content;
        }

        $contents = $items
            ->pluck('content')
            ->filter()
            ->values();

        if ($contents->isEmpty()) {
            return null;
        }

        if ($contents->count() <= 2) {
            return $contents->join('; ');
        }

        return $contents->take(2)->join('; ').'...';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(StudyRecordItem $item): array
    {
        return [
            'id' => $item->id,
            'subject_id' => $item->subject_id,
            'subject_name' => $item->subject?->name,
            'subject_color' => $item->subject?->color,
            'content' => $item->content,
            'minutes' => $item->minutes,
            'hours' => $item->minutes === null ? null : intdiv($item->minutes, 60),
            'time_minutes' => $item->minutes === null ? null : $item->minutes % 60,
            'duration_label' => $this->durationLabel($item->minutes),
            'position' => $item->position,
        ];
    }
}
