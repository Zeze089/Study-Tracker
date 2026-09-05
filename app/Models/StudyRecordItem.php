<?php

namespace App\Models;

use Database\Factories\StudyRecordItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['study_record_id', 'subject_id', 'content', 'minutes', 'position'])]
class StudyRecordItem extends Model
{
    /** @use HasFactory<StudyRecordItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'position' => 'integer',
        ];
    }

    public function studyRecord(): BelongsTo
    {
        return $this->belongsTo(StudyRecord::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
