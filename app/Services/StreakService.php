<?php

namespace App\Services;

use App\Models\StudyRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class StreakService
{
    public function currentStreak(User $user, ?CarbonInterface $today = null): int
    {
        $today = $this->dateForUser($user, $today);
        $records = $this->recordsUntil($user, $today);
        $todayKey = $today->toDateString();

        if ($records->has($todayKey) && $records->get($todayKey)->studied === false) {
            return 0;
        }

        $cursor = $records->has($todayKey) ? $today : $today->subDay();
        $streak = 0;

        while ($cursor->lessThanOrEqualTo($today)) {
            $record = $records->get($cursor->toDateString());

            if ($record === null || $record->studied === false) {
                break;
            }

            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    public function longestStreak(User $user): int
    {
        $records = StudyRecord::query()
            ->whereBelongsTo($user)
            ->where('studied', true)
            ->orderBy('study_date')
            ->get(['study_date']);

        $longest = 0;
        $current = 0;
        $previousDate = null;

        foreach ($records as $record) {
            $date = CarbonImmutable::parse($record->study_date);
            $current = $previousDate?->addDay()->isSameDay($date) ? $current + 1 : 1;
            $longest = max($longest, $current);
            $previousDate = $date;
        }

        return $longest;
    }

    private function dateForUser(User $user, ?CarbonInterface $date): CarbonImmutable
    {
        $timezone = $user->timezone ?: config('app.timezone');

        return ($date instanceof CarbonInterface
            ? CarbonImmutable::parse($date, $timezone)
            : now($timezone)->toImmutable()
        )->startOfDay();
    }

    /**
     * @return Collection<string, StudyRecord>
     */
    private function recordsUntil(User $user, CarbonImmutable $today): Collection
    {
        return StudyRecord::query()
            ->whereBelongsTo($user)
            ->whereDate('study_date', '<=', $today->toDateString())
            ->orderByDesc('study_date')
            ->get(['study_date', 'studied'])
            ->keyBy(fn (StudyRecord $record) => $record->study_date->toDateString());
    }
}
