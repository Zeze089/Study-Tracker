<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\StudyRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GoalProgressService
{
    public function activeProgress(User $user, ?CarbonInterface $today = null): array
    {
        $today = $this->dateForUser($user, $today);

        return Goal::query()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->orderBy('ends_on')
            ->get()
            ->map(fn (Goal $goal) => $this->progressForGoal($goal, $today))
            ->all();
    }

    public function progressForGoal(Goal $goal, ?CarbonInterface $today = null): array
    {
        $today = $this->dateForUser($goal->user, $today);
        $startsOn = CarbonImmutable::parse($goal->starts_on)->startOfDay();
        $endsOn = CarbonImmutable::parse($goal->ends_on)->startOfDay();
        $countUntil = $endsOn->lessThan($today) ? $endsOn : $today;

        $studiedDays = StudyRecord::query()
            ->whereBelongsTo($goal->user)
            ->where('studied', true)
            ->whereBetween('study_date', [$startsOn->toDateString(), $countUntil->toDateString()])
            ->count();

        return [
            'goal' => $goal,
            'studied_days' => $studiedDays,
            'target_days' => $goal->target_days,
            'percentage' => $goal->target_days > 0 ? min(100, round(($studiedDays / $goal->target_days) * 100)) : 0,
            'remaining_days' => max(0, $goal->target_days - $studiedDays),
        ];
    }

    private function dateForUser(User $user, ?CarbonInterface $date): CarbonImmutable
    {
        $timezone = $user->timezone ?: config('app.timezone');

        return CarbonImmutable::parse($date ?? 'now', $timezone)->startOfDay();
    }
}
