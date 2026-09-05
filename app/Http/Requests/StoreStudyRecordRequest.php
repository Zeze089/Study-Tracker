<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudyRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $timezone = $this->user()?->timezone ?? config('app.timezone');
        $today = Carbon::now($timezone)->toDateString();
        $studyRecordId = $this->route('studyRecord')?->id;
        $studyDateRules = ['required', 'date', 'before_or_equal:'.$today];

        if ($studyRecordId) {
            $studyDateRules[] = Rule::unique('study_records', 'study_date')
                ->where('user_id', $this->user()?->id)
                ->ignore($studyRecordId);
        }

        return [
            'study_date' => $studyDateRules,
            'studied' => ['required', 'boolean'],
            'items' => ['nullable', 'array', 'max:12'],
            'items.*.subject_id' => [
                'nullable',
                'integer',
                Rule::exists('subjects', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('active', true),
            ],
            'items.*.content' => ['nullable', 'string', 'max:255'],
            'items.*.hours' => ['nullable', 'integer', 'min:0', 'max:24'],
            'items.*.time_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'items.*.minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'subject_id' => [
                'nullable',
                'integer',
                Rule::exists('subjects', 'id')
                    ->where('user_id', $this->user()?->id)
                    ->where('active', true),
            ],
            'content' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:24'],
            'time_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $totalMinutes = $this->totalStudyMinutes();

                if ($totalMinutes !== null && $totalMinutes > 1440) {
                    $validator->errors()->add('hours', 'O tempo estudado nao pode ultrapassar 24 horas.');
                }

                foreach ($this->input('items', []) as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $itemMinutes = $this->totalMinutesForItem($item);

                    if ($itemMinutes !== null && $itemMinutes > 1440) {
                        $validator->errors()->add("items.$index.hours", 'O tempo de uma materia nao pode ultrapassar 24 horas.');
                    }
                }
            },
        ];
    }

    public function totalStudyMinutes(): ?int
    {
        if ($this->has('items') && is_array($this->input('items'))) {
            $totalMinutes = collect($this->studyItems())
                ->sum(fn (array $item): int => $item['minutes'] ?? 0);

            return $totalMinutes > 0 ? $totalMinutes : null;
        }

        if ($this->filled('minutes')) {
            return (int) $this->input('minutes');
        }

        if (! $this->filled('hours') && ! $this->filled('time_minutes')) {
            return null;
        }

        return ((int) $this->input('hours', 0) * 60) + (int) $this->input('time_minutes', 0);
    }

    /**
     * @return array<int, array{subject_id: int|null, content: string|null, minutes: int|null, position: int}>
     */
    public function studyItems(): array
    {
        if ($this->has('items') && is_array($this->input('items'))) {
            return collect($this->input('items'))
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item, int $index): array => $this->normalizeItem($item, $index + 1))
                ->reject(fn (array $item): bool => $this->itemIsBlank($item))
                ->values()
                ->map(function (array $item, int $index): array {
                    $item['position'] = $index + 1;

                    return $item;
                })
                ->all();
        }

        $legacyItem = $this->normalizeItem([
            'subject_id' => $this->input('subject_id'),
            'content' => $this->input('content'),
            'hours' => $this->input('hours'),
            'time_minutes' => $this->input('time_minutes'),
            'minutes' => $this->input('minutes'),
        ], 1);

        return $this->itemIsBlank($legacyItem) ? [] : [$legacyItem];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{subject_id: int|null, content: string|null, minutes: int|null, position: int}
     */
    private function normalizeItem(array $item, int $position): array
    {
        $content = trim((string) ($item['content'] ?? ''));

        return [
            'subject_id' => filled($item['subject_id'] ?? null) ? (int) $item['subject_id'] : null,
            'content' => $content === '' ? null : $content,
            'minutes' => $this->totalMinutesForItem($item),
            'position' => $position,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function totalMinutesForItem(array $item): ?int
    {
        if (filled($item['minutes'] ?? null)) {
            return (int) $item['minutes'];
        }

        if (! filled($item['hours'] ?? null) && ! filled($item['time_minutes'] ?? null)) {
            return null;
        }

        return ((int) ($item['hours'] ?? 0) * 60) + (int) ($item['time_minutes'] ?? 0);
    }

    /**
     * @param  array{subject_id: int|null, content: string|null, minutes: int|null, position: int}  $item
     */
    private function itemIsBlank(array $item): bool
    {
        return $item['subject_id'] === null
            && $item['content'] === null
            && $item['minutes'] === null;
    }

    public function messages(): array
    {
        return [
            'study_date.before_or_equal' => 'Nao e possivel registrar estudo em uma data futura.',
            'subject_id.exists' => 'A materia selecionada nao esta disponivel para este usuario.',
            'items.*.subject_id.exists' => 'Uma das materias selecionadas nao esta disponivel para este usuario.',
        ];
    }
}
