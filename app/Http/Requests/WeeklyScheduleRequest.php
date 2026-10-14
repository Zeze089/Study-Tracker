<?php

namespace App\Http\Requests;

use App\Models\PlannedStudySession;
use Illuminate\Foundation\Http\FormRequest;

class WeeklyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PlannedStudySession::class) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return ['week' => ['nullable', 'date_format:Y-m-d'], 'date' => ['nullable', 'date_format:Y-m-d']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['week.date_format' => 'Selecione uma semana valida.'];
    }
}
