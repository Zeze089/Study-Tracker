<?php

namespace App\Services;

use App\Models\StudyRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalendarService
{
    public function __construct(
        private StudyStatsService $studyStats,
        private StudyRecordFormatter $studyRecordFormatter
    ) {}

    /**
     * @return array{0: int, 1: int}
     */
    public function resolvePeriod(User $user, mixed $year, mixed $month): array
    {
        $today = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->startOfDay();
        $resolvedYear = $this->integerOrNull($year);
        $resolvedMonth = $this->integerOrNull($month);

        if (
            $resolvedYear === null
            || $resolvedMonth === null
            || $resolvedYear < 1970
            || $resolvedYear > 2100
            || $resolvedMonth < 1
            || $resolvedMonth > 12
        ) {
            return [$today->year, $today->month];
        }

        return [$resolvedYear, $resolvedMonth];
    }

    /**
     * @return array<string, mixed>
     */
    public function month(User $user, int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $user->timezone ?: config('app.timezone'));
        $end = $start->endOfMonth();
        $today = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->startOfDay();

        $records = StudyRecord::query()
            ->with('items.subject:id,name,color')
            ->whereBelongsTo($user)
            ->whereDate('study_date', '>=', $start->toDateString())
            ->whereDate('study_date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (StudyRecord $record) => $record->study_date->toDateString());

        $previousMonth = $start->subMonthNoOverflow();
        $nextMonth = $start->addMonthNoOverflow();

        return [
            'year' => $year,
            'month' => $month,
            'month_name' => $this->monthName($month),
            'label' => $this->monthName($month).' '.$year,
            'starts_on' => $start,
            'ends_on' => $end,
            'today' => $today,
            'records' => $records,
            'weeks' => $this->weeks($start, $end, $today, $records),
            'weekday_labels' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'],
            'summary' => $this->studyStats->monthSummary($user, $start, $records),
            'previous_month' => [
                'year' => $previousMonth->year,
                'month' => $previousMonth->month,
                'label' => $this->monthName($previousMonth->month),
            ],
            'next_month' => [
                'year' => $nextMonth->year,
                'month' => $nextMonth->month,
                'label' => $this->monthName($nextMonth->month),
            ],
            'current_month' => [
                'year' => $today->year,
                'month' => $today->month,
            ],
        ];
    }

    private function integerOrNull(mixed $value): ?int
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? null : $filtered;
    }

    /**
     * @param  Collection<string, StudyRecord>  $records
     * @return array<int, array<int, array<string, mixed>|null>>
     */
    private function weeks(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today, Collection $records): array
    {
        $weeks = [];
        $week = array_fill(0, $start->dayOfWeekIso - 1, null);

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $record = $records->get($date->toDateString());
            $week[] = $this->day($date, $today, $record);

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        if ($week !== []) {
            $weeks[] = array_pad($week, 7, null);
        }

        return $weeks;
    }

    /**
     * @return array<string, mixed>
     */
    private function day(CarbonImmutable $date, CarbonImmutable $today, ?StudyRecord $record): array
    {
        $serialized = $record ? $this->studyRecordFormatter->serialize($record) : null;
        $status = match (true) {
            $record?->studied === true => 'studied',
            $record?->studied === false => 'not_studied',
            default => 'unregistered',
        };

        $statusLabel = match ($status) {
            'studied' => 'Estudou',
            'not_studied' => 'Nao estudou',
            default => 'Nao registrado',
        };

        return [
            'date' => $date->toDateString(),
            'date_label' => $date->format('d/m/Y'),
            'day' => $date->day,
            'is_today' => $date->isSameDay($today),
            'is_future' => $date->greaterThan($today),
            'status' => $status,
            'status_label' => $statusLabel,
            'record' => $serialized,
            'subject_name' => $serialized['subjects_label'] ?? null,
            'subject_color' => $record?->items->first()?->subject?->color ?? $record?->subject?->color,
            'content' => $serialized['content_label'] ?? null,
            'duration_label' => $serialized['duration_label'] ?? null,
            'aria_label' => $date->format('d/m/Y').': '.$statusLabel.($date->isSameDay($today) ? ', hoje' : ''),
        ];
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Marco',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ][$month];
    }
}
