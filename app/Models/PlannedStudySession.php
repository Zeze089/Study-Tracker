<?php

namespace App\Models;

use Database\Factories\PlannedStudySessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'content', 'scheduled_date', 'scheduled_time', 'timezone', 'priority', 'planned_minutes'])]
class PlannedStudySession extends Model
{
    /** @use HasFactory<PlannedStudySessionFactory> */
    use HasFactory;

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'planned_minutes' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function studyRecordItems(): HasMany
    {
        return $this->hasMany(StudyRecordItem::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(PlannedSessionChange::class)->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    public function planningSnapshot(): array
    {
        return [
            'subject_id' => $this->subject_id, 'content' => $this->content,
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'scheduled_time' => $this->scheduled_time ? substr($this->scheduled_time, 0, 5) : null,
            'timezone' => $this->timezone, 'priority' => $this->priority,
            'planned_minutes' => $this->planned_minutes,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }

    public function revision(): string
    {
        $lastChange = $this->relationLoaded('changes') ? $this->getRelation('changes')->max('id') : $this->changes()->max('id');

        return hash('sha256', json_encode([$this->id, $this->planningSnapshot(), (int) ($lastChange ?? 0)], JSON_THROW_ON_ERROR));
    }
}
