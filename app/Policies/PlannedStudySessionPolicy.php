<?php

namespace App\Policies;

use App\Models\PlannedStudySession;
use App\Models\User;
use Carbon\CarbonImmutable;

class PlannedStudySessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, PlannedStudySession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function update(User $user, PlannedStudySession $session): bool
    {
        return $this->view($user, $session)
            && $session->cancelled_at === null
            && $session->scheduled_date->toDateString() >= CarbonImmutable::now($session->timezone)->toDateString();
    }

    public function cancel(User $user, PlannedStudySession $session): bool
    {
        return $this->view($user, $session) && $session->cancelled_at === null;
    }

    public function execute(User $user, PlannedStudySession $session): bool
    {
        return $this->view($user, $session) && $session->cancelled_at === null
            && $session->subject->user_id === $user->id && $session->subject->active;
    }

    public function reschedule(User $user, PlannedStudySession $session): bool
    {
        return $this->view($user, $session) && $session->cancelled_at === null;
    }
}
