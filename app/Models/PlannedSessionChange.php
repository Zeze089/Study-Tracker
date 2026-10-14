<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'actor_name', 'action', 'before', 'after', 'reason'])]
class PlannedSessionChange extends Model
{
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function plannedStudySession(): BelongsTo
    {
        return $this->belongsTo(PlannedStudySession::class);
    }
}
