<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReschedulePlannedSessionRequest extends FormRequest
{
    protected $errorBag = 'reschedule';

    public function authorize(): bool
    {
        return $this->user()?->can('reschedule', $this->route('plannedSession')) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        $today = CarbonImmutable::now($this->route('plannedSession')->timezone)->toDateString();

        return [
            'scheduled_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
            'revision' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('scheduled_time')) {
                return;
            }
            $local = $this->input('scheduled_date').' '.$this->input('scheduled_time');
            if (CarbonImmutable::parse($local, $this->route('plannedSession')->timezone)->format('Y-m-d H:i') !== $local) {
                $validator->errors()->add('scheduled_time', 'Este horario nao existe no fuso da sessao.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'scheduled_date.after_or_equal' => 'Selecione uma data atual ou futura.',
            'reason.required' => 'Informe o motivo do reagendamento.',
            'revision.required' => 'Reabra a sessao antes de reagendar.',
        ];
    }
}
