<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;
use Carbon\CarbonImmutable;

class GoalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Goal $goal): bool
    {
        return $goal->user_id === $user->id
            && $goal->active
            && $goal->ends_on->toDateString() >= CarbonImmutable::now($user->timezone ?: config('app.timezone'))->toDateString();
    }

    public function archive(User $user, Goal $goal): bool
    {
        return $goal->user_id === $user->id && $goal->active;
    }
}
