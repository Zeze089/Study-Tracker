<?php

namespace App\Models;

use Database\Factories\StudyRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'subject_id', 'study_date', 'studied', 'content', 'minutes', 'notes'])]
class StudyRecord extends Model
{
    /** @use HasFactory<StudyRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'study_date' => 'date',
            'studied' => 'boolean',
            'minutes' => 'integer',
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

    public function items(): HasMany
    {
        return $this->hasMany(StudyRecordItem::class)->orderBy('position');
    }
}
