<?php

namespace App\Services;

use App\Models\StudyRecord;
use App\Models\StudyRecordItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class StudyStatsService
{
    public function __construct(private StudyRecordFormatter $studyRecordFormatter) {}

    public function monthSummary(User $user, ?CarbonInterface $date = null, ?Collection $records = null): array
    {
        [$start, $end, $elapsedDays] = $this->periodWithinToday(
            $user,
            $date,
            fn (CarbonImmutable $date) => $date->startOfMonth(),
            fn (CarbonImmutable $date) => $date->endOfMonth()
        );

        return $this->summaryForPeriod($user, $start, $end, $elapsedDays, $records);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardData(User $user, ?CarbonInterface $date = null): array
    {
        $today = $this->dateForUser($user, $date);
        $monthSummary = $this->monthSummary($user, $today);
        $topSubjects = $this->subjectSummary($user, $today);

        return [
            'today' => [
                'date' => $today->toDateString(),
                'date_label' => $today->format('d/m/Y'),
            ],
            'month_label' => $this->monthName($today->month),
            'month_summary' => $monthSummary,
            'top_subject' => $topSubjects[0] ?? null,
            'top_subjects' => $topSubjects,
            'recent_chart' => $this->recentChart($user, $today),
            'recent_activity' => $this->recentActivity($user, $today),
            'has_records' => StudyRecord::query()
                ->whereBelongsTo($user)
                ->whereDate('study_date', '<=', $today->toDateString())
                ->exists(),
        ];
    }

    public function yearSummary(User $user, ?CarbonInterface $date = null): array
    {
        [$start, $end, $elapsedDays] = $this->periodWithinToday(
            $user,
            $date,
            fn (CarbonImmutable $date) => $date->startOfYear(),
            fn (CarbonImmutable $date) => $date->endOfYear()
        );

        return $this->summaryForPeriod($user, $start, $end, $elapsedDays);
    }

    public function subjectSummary(User $user, ?CarbonInterface $date = null, int $limit = 5): array
    {
        $targetDate = $this->dateForUser($user, $date);
        $start = $targetDate->startOfMonth();
        $today = $this->dateForUser($user, null);
        $end = $targetDate->endOfMonth()->lessThan($today) ? $targetDate->endOfMonth() : $today;

        return StudyRecordItem::query()
            ->with('subject:id,name,color')
            ->join('study_records', 'study_record_items.study_record_id', '=', 'study_records.id')
            ->where('study_records.user_id', $user->id)
            ->where('study_records.studied', true)
            ->whereDate('study_records.study_date', '>=', $start->toDateString())
            ->whereDate('study_records.study_date', '<=', $end->toDateString())
            ->selectRaw('study_record_items.subject_id, COUNT(DISTINCT study_record_items.study_record_id) as records_count, COALESCE(SUM(study_record_items.minutes), 0) as total_minutes')
            ->groupBy('study_record_items.subject_id')
            ->orderByDesc('total_minutes')
            ->orderByDesc('records_count')
            ->orderBy('study_record_items.subject_id')
            ->limit($limit)
            ->get()
            ->map(fn (StudyRecordItem $item) => [
                'id' => $item->subject_id,
                'name' => $item->subject?->name ?? 'Sem materia',
                'color' => $item->subject?->color ?? '#64748b',
                'records_count' => (int) $item->records_count,
                'total_minutes' => (int) $item->total_minutes,
                'duration_label' => $this->studyRecordFormatter->durationLabelOrZero((int) $item->total_minutes),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function topSubject(User $user, ?CarbonInterface $date = null): ?array
    {
        return $this->subjectSummary($user, $date, 1)[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentChart(User $user, ?CarbonInterface $date = null): array
    {
        $today = $this->dateForUser($user, $date);
        $start = $today->subDays(6);

        $records = StudyRecord::query()
            ->whereBelongsTo($user)
            ->where('studied', true)
            ->whereDate('study_date', '>=', $start->toDateString())
            ->whereDate('study_date', '<=', $today->toDateString())
            ->selectRaw('study_date, COALESCE(SUM(minutes), 0) as total_minutes')
            ->groupBy('study_date')
            ->get()
            ->mapWithKeys(fn (StudyRecord $record) => [
                CarbonImmutable::parse($record->study_date)->toDateString() => (int) $record->total_minutes,
            ]);

        $days = [];

        for ($dateCursor = $start; $dateCursor->lessThanOrEqualTo($today); $dateCursor = $dateCursor->addDay()) {
            $dateKey = $dateCursor->toDateString();
            $minutes = (int) ($records[$dateKey] ?? 0);

            $days[] = [
                'date' => $dateKey,
                'label' => $this->weekdayLabel($dateCursor),
                'minutes' => $minutes,
                'duration_label' => $this->studyRecordFormatter->durationLabelOrZero($minutes),
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentActivity(User $user, ?CarbonInterface $date = null, int $limit = 5): array
    {
        $today = $this->dateForUser($user, $date);

        return StudyRecord::query()
            ->with('items.subject:id,name,color')
            ->whereBelongsTo($user)
            ->whereDate('study_date', '<=', $today->toDateString())
            ->orderByDesc('study_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (StudyRecord $record): array {
                $serialized = $this->studyRecordFormatter->serialize($record);

                return [
                    'id' => $record->id,
                    'date' => $record->study_date->toDateString(),
                    'date_label' => $record->study_date->format('d/m'),
                    'studied' => $record->studied,
                    'status_label' => $record->studied ? 'Estudou' : 'Nao estudou',
                    'subject_name' => $serialized['subjects_label'] ?? 'Sem materia',
                    'subject_color' => $serialized['subject_color'] ?? '#64748b',
                    'content' => $serialized['content_label'],
                    'minutes' => $serialized['minutes'],
                    'duration_label' => $this->studyRecordFormatter->durationLabelOrZero($serialized['minutes']),
                    'notes' => $record->notes,
                    'items' => $serialized['items'],
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function studyByWeek(User $user, ?CarbonInterface $date = null): array
    {
        $targetDate = $this->dateForUser($user, $date);
        $start = $targetDate->startOfMonth();
        $today = $this->dateForUser($user, null);
        $end = $targetDate->endOfMonth()->lessThan($today) ? $targetDate->endOfMonth() : $today;

        $records = StudyRecord::query()
            ->whereBelongsTo($user)
            ->where('studied', true)
            ->whereDate('study_date', '>=', $start->toDateString())
            ->whereDate('study_date', '<=', $end->toDateString())
            ->get(['study_date', 'minutes']);

        $weeks = [];
        $weekNumber = 1;

        for ($weekStart = $start; $weekStart->lessThanOrEqualTo($end); $weekStart = $weekStart->addWeek()) {
            $weekEnd = $weekStart->addDays(6)->greaterThan($end) ? $end : $weekStart->addDays(6);
            $minutes = (int) $records
                ->filter(function (StudyRecord $record) use ($weekStart, $weekEnd): bool {
                    $studyDate = CarbonImmutable::parse($record->study_date);

                    return $studyDate->betweenIncluded($weekStart, $weekEnd);
                })
                ->sum('minutes');

            $weeks[] = [
                'label' => 'Semana '.$weekNumber,
                'starts_on' => $weekStart->toDateString(),
                'ends_on' => $weekEnd->toDateString(),
                'minutes' => $minutes,
                'duration_label' => $this->studyRecordFormatter->durationLabelOrZero($minutes),
            ];
            $weekNumber++;
        }

        return $weeks;
    }

    private function summaryForPeriod(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $elapsedDays,
        ?Collection $records = null
    ): array {
        $records ??= StudyRecord::query()
            ->whereBelongsTo($user)
            ->whereDate('study_date', '>=', $start->toDateString())
            ->whereDate('study_date', '<=', $end->toDateString())
            ->get(['study_date', 'studied', 'minutes']);

        if ($records->isNotEmpty()) {
            $records = $records->filter(function (StudyRecord $record) use ($start, $end): bool {
                $studyDate = CarbonImmutable::parse($record->study_date);

                return $studyDate->betweenIncluded($start, $end);
            });
        }

        $studiedRecords = $records->where('studied', true);
        $studiedDays = $studiedRecords->count();
        $notStudiedDays = $records->where('studied', false)->count();
        $totalMinutes = (int) $studiedRecords->sum('minutes');

        return [
            'elapsed_days' => $elapsedDays,
            'registered_days' => $records->count(),
            'studied_days' => $studiedDays,
            'not_studied_days' => $notStudiedDays,
            'unregistered_days' => max(0, $elapsedDays - $records->count()),
            'total_minutes' => $totalMinutes,
            'total_hours' => (float) round($totalMinutes / 60, 1),
            'total_hours_label' => $this->studyRecordFormatter->durationLabelOrZero($totalMinutes),
            'consistency' => $elapsedDays > 0 ? (int) round(($studiedDays / $elapsedDays) * 100) : 0,
        ];
    }

    private function periodWithinToday(User $user, ?CarbonInterface $date, callable $startResolver, callable $endResolver): array
    {
        $targetDate = $this->dateForUser($user, $date);
        $today = $this->dateForUser($user, null);
        $start = $startResolver($targetDate);
        $periodEnd = $endResolver($targetDate);
        $end = $periodEnd->lessThan($today) ? $periodEnd : $today;
        $elapsedDays = $start->greaterThan($today) ? 0 : $start->diffInDays($end) + 1;

        return [$start, $end, $elapsedDays];
    }

    private function dateForUser(User $user, ?CarbonInterface $date): CarbonImmutable
    {
        $timezone = $user->timezone ?: config('app.timezone');

        return ($date instanceof CarbonInterface
            ? CarbonImmutable::parse($date, $timezone)
            : now($timezone)->toImmutable()
        )->startOfDay();
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

    private function weekdayLabel(CarbonImmutable $date): string
    {
        return [
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sab',
            7 => 'Dom',
        ][$date->dayOfWeekIso];
    }
}
