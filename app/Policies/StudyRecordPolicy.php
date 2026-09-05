<?php

namespace App\Policies;

use App\Models\StudyRecord;
use App\Models\User;

class StudyRecordPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StudyRecord $studyRecord): bool
    {
        return $studyRecord->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StudyRecord $studyRecord): bool
    {
        return $studyRecord->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StudyRecord $studyRecord): bool
    {
        return $studyRecord->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StudyRecord $studyRecord): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StudyRecord $studyRecord): bool
    {
        return false;
    }
}
