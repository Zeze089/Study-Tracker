@props(['date', 'record' => null, 'subjects', 'mode' => 'panel'])

@php
    $initialRecord = $record ? app(\App\Services\StudyRecordFormatter::class)->serialize($record) : null;

    $subjectOptions = $subjects->map(fn ($subject) => [
        'id' => $subject->id,
        'name' => $subject->name,
        'color' => $subject->color,
    ])->values();
@endphp

<div
    x-data="studyRecordForm({
        storeUrl: @js(route('study-records.store')),
        csrfToken: @js(csrf_token()),
        date: @js($date->toDateString()),
        dateLabel: @js($date->format('d/m/Y')),
        record: @js($initialRecord),
        subjects: @js($subjectOptions),
    })"
    x-on:open-study-record.window="openModal($event.detail)"
>
    @if ($mode === 'panel')
        <section {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white p-6 shadow-sm']) }}>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Hoje</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold text-slate-950">{{ $date->format('d/m/Y') }}</h2>
                        <span class="rounded-md px-2.5 py-1 text-xs font-semibold" :class="statusClasses()" x-text="statusLabel()"></span>
                    </div>
                </div>

                <button
                    type="button"
                    x-on:click="openModal()"
                    class="inline-flex items-center justify-center rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                    x-text="record ? 'Editar registro de hoje' : '+ Registrar estudo de hoje'"
                ></button>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-3" x-show="record" x-cloak>
                <div class="rounded-md bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase text-slate-500">Materias</p>
                    <p class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="subjectLabel()"></p>
                </div>
                <div class="rounded-md bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase text-slate-500">Itens</p>
                    <p class="mt-1 truncate text-sm font-semibold text-slate-900" x-text="contentLabel()"></p>
                </div>
                <div class="rounded-md bg-slate-50 p-4">
                    <p class="text-xs font-medium uppercase text-slate-500">Tempo</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900" x-text="durationLabel()"></p>
                </div>
            </div>

            <div class="mt-5" x-show="!record" x-cloak>
                <x-empty-panel title="Nenhum registro ainda" body="Registre apenas se estudou ou nao; materia, tempo e observacao podem ficar vazios." class="bg-slate-50" />
            </div>

            <p class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700" x-show="successMessage" x-text="successMessage" x-cloak></p>
            <p class="mt-4 rounded-md bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700" x-show="errorMessage && !modalOpen" x-text="errorMessage" x-cloak></p>
        </section>
    @endif

    <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" x-show="modalOpen" x-cloak>
        <div class="fixed inset-0 bg-slate-950/50" x-on:click="closeModal()"></div>

        <div class="relative mx-auto mt-10 w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl">
            <form x-on:submit.prevent="save()">
                <div class="border-b border-slate-200 px-6 py-5">
                    <p class="text-sm font-medium text-teal-700" x-text="selectedDateLabel"></p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-950" x-text="record ? 'Editar registro' : 'Registrar estudo'"></h2>
                </div>

                <div class="space-y-5 px-6 py-5">
                    <div>
                        <label class="text-sm font-semibold text-slate-900">Voce estudou?</label>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <label class="flex cursor-pointer items-center justify-center rounded-md border px-4 py-3 text-sm font-semibold transition" :class="form.studied === '1' ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                                <input type="radio" value="1" x-model="form.studied" class="sr-only">
                                Sim
                            </label>
                            <label class="flex cursor-pointer items-center justify-center rounded-md border px-4 py-3 text-sm font-semibold transition" :class="form.studied === '0' ? 'border-rose-500 bg-rose-50 text-rose-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                                <input type="radio" value="0" x-model="form.studied" class="sr-only">
                                Nao
                            </label>
                        </div>
                        <p class="mt-2 text-sm text-rose-600" x-show="firstError('studied')" x-text="firstError('studied')"></p>
                    </div>

                    <div x-show="form.studied === '1'" x-cloak>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <label class="text-sm font-semibold text-slate-900">Materias estudadas</label>
                                <p class="mt-1 text-sm text-slate-500">Adicione uma linha para cada materia/conteudo do dia.</p>
                            </div>
                            <button type="button" x-on:click="addItem()" class="inline-flex shrink-0 items-center justify-center rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-800 transition hover:bg-teal-100 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                                + Adicionar
                            </button>
                        </div>

                        <div class="mt-4 space-y-4">
                            <template x-for="(item, index) in form.items" :key="item.key">
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-900">Item <span x-text="index + 1"></span></p>
                                        <button type="button" x-on:click="removeItem(index)" x-show="form.items.length > 1" class="text-sm font-semibold text-rose-700 transition hover:text-rose-800">
                                            Remover
                                        </button>
                                    </div>

                                    <div class="mt-4 grid gap-4">
                                        <div>
                                            <label class="text-sm font-semibold text-slate-900">Materia / Area</label>
                                            <select x-model="item.subject_id" class="mt-2 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                                <option value="">Sem materia</option>
                                                <template x-for="subject in subjects" :key="subject.id">
                                                    <option :value="subject.id" x-text="subject.name"></option>
                                                </template>
                                            </select>
                                            <p class="mt-2 text-sm text-rose-600" x-show="firstError(`items.${index}.subject_id`)" x-text="firstError(`items.${index}.subject_id`)"></p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-semibold text-slate-900">Conteudo estudado</label>
                                            <input type="text" maxlength="255" x-model="item.content" class="mt-2 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" placeholder="Opcional">
                                            <p class="mt-2 text-sm text-rose-600" x-show="firstError(`items.${index}.content`)" x-text="firstError(`items.${index}.content`)"></p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-semibold text-slate-900">Tempo deste item</label>
                                            <div class="mt-2 grid grid-cols-2 gap-3">
                                                <div>
                                                    <input type="number" min="0" max="24" step="1" x-model="item.hours" placeholder="0" class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                                    <p class="mt-1 text-xs text-slate-500">horas</p>
                                                </div>
                                                <div>
                                                    <input type="number" min="0" max="59" step="1" x-model="item.time_minutes" placeholder="0" class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                                    <p class="mt-1 text-xs text-slate-500">minutos</p>
                                                </div>
                                            </div>
                                            <p class="mt-2 text-sm text-rose-600" x-show="firstError(`items.${index}.hours`)" x-text="firstError(`items.${index}.hours`)"></p>
                                            <p class="mt-2 text-sm text-rose-600" x-show="firstError(`items.${index}.time_minutes`)" x-text="firstError(`items.${index}.time_minutes`)"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p class="mt-3 text-sm font-medium text-slate-600">Tempo total: <span x-text="itemsDurationLabel()"></span></p>
                        <p class="mt-2 text-sm text-rose-600" x-show="firstError('hours')" x-text="firstError('hours')"></p>
                    </div>

                    <div>
                        <label for="notes" class="text-sm font-semibold text-slate-900">Observacao</label>
                        <textarea id="notes" rows="4" x-model="form.notes" class="mt-2 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" placeholder="Opcional"></textarea>
                        <p class="mt-2 text-sm text-rose-600" x-show="firstError('notes')" x-text="firstError('notes')"></p>
                    </div>

                    <p class="rounded-md bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700" x-show="errorMessage" x-text="errorMessage"></p>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="closeModal()" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" :disabled="submitting">
                        Cancelar
                    </button>
                    <button type="submit" class="rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-70" :disabled="submitting">
                        <span x-show="!submitting">Salvar</span>
                        <span x-show="submitting">Salvando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
