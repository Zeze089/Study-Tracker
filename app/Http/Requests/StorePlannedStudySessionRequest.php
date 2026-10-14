<?php

namespace App\Http\Requests;

use App\Models\PlannedStudySession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlannedStudySessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('content'))) {
            $this->merge(['content' => trim($this->input('content'))]);
        }
    }

    public function authorize(): bool
    {
        $session = $this->route('plannedSession');

        return $this->user() !== null && ($session instanceof PlannedStudySession
            ? $this->user()->can('update', $session)
            : $this->user()->can('create', PlannedStudySession::class));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $session = $this->route('plannedSession');
        $today = CarbonImmutable::now($session?->timezone ?? $this->user()->timezone ?: config('app.timezone'))->toDateString();

        return [
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')
                ->where('user_id', $this->user()->id)
                ->where(fn ($query) => $query->where('active', true)->when($session !== null, fn ($query) => $query->orWhere('id', $session->subject_id)))],
            'content' => ['required', 'string', 'max:255'],
            'scheduled_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'priority' => ['required', Rule::in([PlannedStudySession::PRIORITY_LOW, PlannedStudySession::PRIORITY_NORMAL, PlannedStudySession::PRIORITY_HIGH])],
            'planned_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'revision' => [Rule::requiredIf($session !== null), 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'change_reason' => [Rule::requiredIf($session !== null && (
                $this->input('scheduled_date') !== $session->scheduled_date->toDateString()
                || ($this->input('scheduled_time') ?: null) !== ($session->scheduled_time ? substr($session->scheduled_time, 0, 5) : null)
            )), 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('scheduled_time')) {
                    return;
                }
                $timezone = $this->route('plannedSession')?->timezone ?? $this->user()->timezone ?: config('app.timezone');
                $local = $this->input('scheduled_date').' '.$this->input('scheduled_time');
                if (CarbonImmutable::parse($local, $timezone)->format('Y-m-d H:i') !== $local) {
                    $validator->errors()->add('scheduled_time', 'Este horario nao existe no fuso da sessao.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'subject_id.required' => 'Selecione uma materia.',
            'subject_id.exists' => 'A materia selecionada nao esta disponivel para este usuario.',
            'content.required' => 'Informe o conteudo planejado.',
            'content.max' => 'O conteudo deve ter no maximo 255 caracteres.',
            'scheduled_date.required' => 'Informe a data planejada.',
            'scheduled_date.date_format' => 'Informe uma data valida.',
            'scheduled_date.after_or_equal' => 'Selecione uma data atual ou futura.',
            'scheduled_time.date_format' => 'Informe um horario valido.',
            'priority.required' => 'Selecione a prioridade.',
            'priority.in' => 'Selecione uma prioridade valida.',
            'planned_minutes.required' => 'Informe os minutos previstos.',
            'planned_minutes.integer' => 'Os minutos previstos devem ser um numero inteiro.',
            'planned_minutes.min' => 'Informe pelo menos um minuto.',
            'planned_minutes.max' => 'O tempo previsto nao pode ultrapassar 24 horas.',
            'change_reason.required' => 'Informe o motivo ao mudar a data ou o horario.',
        ];
    }
}
