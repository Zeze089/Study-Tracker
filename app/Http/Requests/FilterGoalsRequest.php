<?php

namespace App\Http\Requests;

use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterGoalsRequest extends FormRequest
{
    protected $errorBag = 'filters';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Goal::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(['all', 'current', 'history'])],
            'type' => ['nullable', Rule::in([Goal::TYPE_DAILY, Goal::TYPE_WEEKLY, Goal::TYPE_MONTHLY])],
            'metric' => ['nullable', Rule::in([Goal::METRIC_DAYS, Goal::METRIC_MINUTES])],
            'status' => ['nullable', Rule::in(['planned', 'ongoing', 'achieved', 'closed', 'archived'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 30, 60])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'view.in' => 'Selecione uma visualizacao valida.',
            'type.in' => 'Selecione um periodo valido.',
            'metric.in' => 'Selecione dias ou tempo.',
            'status.in' => 'Selecione um estado valido.',
            'from.date_format' => 'Informe uma data inicial valida.',
            'until.date_format' => 'Informe uma data final valida.',
            'until.after_or_equal' => 'O fim do filtro nao pode ser anterior ao inicio.',
            'per_page.in' => 'Selecione 15, 30 ou 60 metas por pagina.',
        ];
    }
}
