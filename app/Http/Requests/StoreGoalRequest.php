<?php

namespace App\Http\Requests;

use App\Models\Goal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('metric')) {
            $this->merge(['metric' => $this->route('goal')?->metric ?? Goal::METRIC_DAYS]);
        }
    }

    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $this->user() !== null && ($goal instanceof Goal
            ? $this->user()->can('update', $goal)
            : $this->user()->can('create', Goal::class));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Goal::TYPE_DAILY, Goal::TYPE_WEEKLY, Goal::TYPE_MONTHLY])],
            'metric' => ['required', Rule::in([Goal::METRIC_DAYS, Goal::METRIC_MINUTES])],
            'target_days' => ['exclude_unless:metric,days', 'required', 'integer', 'min:1', 'max:31'],
            'target_minutes' => ['exclude_unless:metric,minutes', 'required', 'integer', 'min:1', 'max:44640'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $timezone = $this->user()->timezone ?: config('app.timezone');
                $start = CarbonImmutable::parse($this->input('starts_on'), $timezone);
                $end = CarbonImmutable::parse($this->input('ends_on'), $timezone);
                [$expectedStart, $expectedEnd, $message] = match ($this->input('type')) {
                    Goal::TYPE_DAILY => [$start, $start, 'A meta diaria deve iniciar e terminar na mesma data.'],
                    Goal::TYPE_WEEKLY => [$start->startOfWeek(CarbonInterface::MONDAY), $start->startOfWeek(CarbonInterface::MONDAY)->addDays(6), 'A meta semanal deve abranger de segunda-feira a domingo.'],
                    default => [$start->startOfMonth(), $start->endOfMonth(), 'A meta mensal deve abranger o mes completo.'],
                };

                if ($start->toDateString() !== $expectedStart->toDateString()
                    || $end->toDateString() !== $expectedEnd->toDateString()) {
                    $validator->errors()->add('ends_on', $message);
                }

                $capacity = (int) $start->diffInDays($end) + 1;
                if ($this->input('metric') === Goal::METRIC_DAYS && $this->integer('target_days') > $capacity) {
                    $validator->errors()->add('target_days', 'Os dias-alvo nao podem ultrapassar os dias do periodo.');
                }
                if ($this->input('metric') === Goal::METRIC_MINUTES && $this->integer('target_minutes') > $capacity * 1440) {
                    $validator->errors()->add('target_minutes', 'Os minutos-alvo nao podem ultrapassar 24 horas por dia do periodo.');
                }

                if ($end->toDateString() < CarbonImmutable::now($timezone)->toDateString()) {
                    $validator->errors()->add('ends_on', 'Selecione um periodo atual ou futuro.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.required' => 'Selecione o tipo da meta.',
            'type.in' => 'Selecione uma meta diaria, semanal ou mensal.',
            'metric.required' => 'Selecione a metrica.',
            'metric.in' => 'Selecione dias ou minutos.',
            'target_days.required' => 'Informe os dias-alvo.',
            'target_days.integer' => 'Os dias-alvo devem ser um numero inteiro.',
            'target_days.min' => 'Informe pelo menos um dia.',
            'target_days.max' => 'Informe no maximo 31 dias.',
            'target_minutes.required' => 'Informe os minutos-alvo.',
            'target_minutes.integer' => 'Os minutos-alvo devem ser um numero inteiro.',
            'target_minutes.min' => 'Informe pelo menos um minuto.',
            'target_minutes.max' => 'Informe no maximo 44640 minutos.',
            'starts_on.required' => 'Informe o inicio do periodo.',
            'starts_on.date_format' => 'Informe uma data inicial valida.',
            'ends_on.required' => 'Informe o fim do periodo.',
            'ends_on.date_format' => 'Informe uma data final valida.',
            'ends_on.after_or_equal' => 'O fim nao pode ser anterior ao inicio.',
        ];
    }
}
