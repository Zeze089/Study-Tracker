<?php

namespace App\Services;

use App\Models\StudyRecord;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class DashboardService
{
    public function __construct(
        private StudyStatsService $studyStats,
        private StreakService $streaks,
        private StudyRecordFormatter $studyRecordFormatter
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(User $user, ?CarbonInterface $date = null): array
    {
        $today = $this->dateForUser($user, $date);

        return [
            'dashboard' => $this->stats($user, $today),
            'today' => $today,
            'todayRecord' => $this->todayRecord($user, $today),
            'subjects' => $this->subjects($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(User $user, ?CarbonInterface $date = null): array
    {
        $today = $this->dateForUser($user, $date);
        $stats = $this->studyStats->dashboardData($user, $today);
        $todayRecord = $this->todayRecord($user, $today);

        return [
            ...$stats,
            'today_record' => $todayRecord ? $this->studyRecordFormatter->serialize($todayRecord) : null,
            'current_streak' => $this->streaks->currentStreak($user, $today),
            'longest_streak' => $this->streaks->longestStreak($user),
        ];
    }

    private function todayRecord(User $user, CarbonImmutable $today): ?StudyRecord
    {
        return $user->studyRecords()
            ->with('items.subject:id,name,color')
            ->whereDate('study_date', $today->toDateString())
            ->first();
    }

    /**
     * @return EloquentCollection<int, Subject>
     */
    private function subjects(User $user): EloquentCollection
    {
        return $user->subjects()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);
    }

    private function dateForUser(User $user, ?CarbonInterface $date): CarbonImmutable
    {
        $timezone = $user->timezone ?: config('app.timezone');

        return ($date instanceof CarbonInterface
            ? CarbonImmutable::parse($date, $timezone)
            : now($timezone)->toImmutable()
        )->startOfDay();
    }
}
