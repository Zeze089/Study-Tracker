<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StorePlannedExecutionRequest extends FormRequest
{
    protected $errorBag = 'execution';

    public function authorize(): bool
    {
        return $this->user()?->can('execute', $this->route('plannedSession')) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        $today = CarbonImmutable::now($this->user()->timezone ?: config('app.timezone'))->toDateString();

        return [
            'study_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'actual_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'content' => ['nullable', 'string', 'max:255'],
            'submission_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'study_date.before_or_equal' => 'Nao e possivel registrar execucao futura.',
            'study_date.date_format' => 'Informe uma data valida.',
            'actual_minutes.required' => 'Informe os minutos realmente estudados.',
            'actual_minutes.integer' => 'Informe minutos inteiros.',
            'actual_minutes.min' => 'Informe pelo menos um minuto real.',
            'actual_minutes.max' => 'A execucao nao pode ultrapassar 24 horas.',
            'submission_key.uuid' => 'Reabra o formulario para gerar um envio valido.',
        ];
    }
}
