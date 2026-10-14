<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GoalService
{
    /**
     * @param  array{type: string, metric?: string, target_days?: int|string, target_minutes?: int|string, starts_on: string, ends_on: string}  $data
     */
    public function save(User $user, array $data, ?Goal $goal = null): Goal
    {
        return DB::transaction(function () use ($user, $data, $goal): Goal {
            // Lock an existing parent row even when the user has no goals yet.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($goal !== null) {
                $goal = $user->goals()->whereKey($goal->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('update', $goal);
            } else {
                Gate::forUser($user)->authorize('create', Goal::class);
            }

            $metric = $data['metric'] ?? $goal?->metric ?? Goal::METRIC_DAYS;
            $data['metric'] = $metric;
            $data['target_days'] = $metric === Goal::METRIC_DAYS ? (int) $data['target_days'] : 0;
            $data['target_minutes'] = $metric === Goal::METRIC_MINUTES ? (int) $data['target_minutes'] : null;

            $overlap = $user->goals()
                ->where('active', true)
                ->where('type', $data['type'])
                ->where('metric', $metric)
                ->whereDate('starts_on', '<=', $data['ends_on'])
                ->whereDate('ends_on', '>=', $data['starts_on'])
                ->when($goal !== null, fn ($query) => $query->whereKeyNot($goal->id))
                ->lockForUpdate()
                ->first();

            if ($overlap !== null) {
                throw ValidationException::withMessages([
                    'starts_on' => 'Ja existe uma meta ativa deste tipo e metrica no periodo selecionado.',
                ]);
            }

            if ($goal !== null) {
                $goal->update($data);

                return $goal;
            }

            return $user->goals()->create([...$data, 'active' => true]);
        }, 3);
    }

    public function archive(User $user, Goal $goal): void
    {
        DB::transaction(function () use ($user, $goal): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $goal = $user->goals()->whereKey($goal->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('archive', $goal);
            $goal->update(['active' => false]);
        }, 3);
    }
}
