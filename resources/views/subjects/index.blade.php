<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-teal-700">Areas de estudo</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950">Materias</h1>
            </div>

            <button type="button" x-data x-on:click="$dispatch('open-subject-modal')" class="inline-flex items-center justify-center rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                + Nova materia
            </button>
        </div>
    </x-slot>

    @php
        $subjectPayload = $subjects->map(fn ($subject) => [
            'id' => $subject->id,
            'name' => $subject->name,
            'color' => $subject->color,
            'active' => $subject->active,
            'study_records_count' => $subject->study_records_count,
            'update_url' => route('subjects.update', $subject),
            'delete_url' => route('subjects.destroy', $subject),
        ])->values();
        $oldSubject = $subjectPayload->firstWhere('id', (int) old('subject_id'));

        $initialForm = [
            'id' => $oldSubject['id'] ?? null,
            'name' => old('name', ''),
            'color' => old('color', '#14b8a6'),
            'active' => old('active', '1') === '1',
            'update_url' => $oldSubject['update_url'] ?? null,
            'study_records_count' => $oldSubject['study_records_count'] ?? 0,
        ];
    @endphp

    <div
        class="space-y-6"
        x-data="subjectManager({
            storeUrl: @js(route('subjects.store')),
            subjects: @js($subjectPayload),
            initialForm: @js($initialForm),
            initialModalOpen: @js($errors->any()),
        })"
        x-on:open-subject-modal.window="openCreate()"
    >
        @if (session('status') === 'subject-saved')
            <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">Materia salva com sucesso.</p>
        @endif

        @if (session('status') === 'subject-deleted')
            <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">Materia excluida com sucesso.</p>
        @endif

        @if (session('error'))
            <p class="rounded-md bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ session('error') }}</p>
        @endif

        @if ($subjects->isEmpty())
            <section class="rounded-lg border border-dashed border-teal-200 bg-teal-50 p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Nenhuma materia cadastrada.</h2>
                        <p class="mt-1 text-sm text-slate-600">Crie sua primeira area para organizar os proximos estudos.</p>
                    </div>
                    <button type="button" x-on:click="openCreate()" class="inline-flex items-center justify-center rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                        + Nova materia
                    </button>
                </div>
            </section>
        @else
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($subjects as $subject)
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="h-4 w-4 shrink-0 rounded-full ring-2 ring-white" style="background-color: {{ $subject->color }}"></span>
                                <div class="min-w-0">
                                    <h2 class="truncate text-base font-semibold text-slate-950">{{ $subject->name }}</h2>
                                    <p class="mt-1 text-sm text-slate-500">{{ $subject->study_records_count }} registros vinculados</p>
                                </div>
                            </div>
                            <span class="rounded-md px-2.5 py-1 text-xs font-semibold {{ $subject->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $subject->active ? 'Ativa' : 'Inativa' }}
                            </span>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $subject->color }}</span>
                            <div class="flex items-center gap-2">
                                <button type="button" x-on:click="openEdit({{ Js::from($subjectPayload->firstWhere('id', $subject->id)) }})" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                                    Editar
                                </button>
                                <form method="POST" action="{{ route('subjects.destroy', $subject) }}" onsubmit="return confirm('Excluir esta materia? Esta acao nao pode ser desfeita.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                                        Excluir
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" x-show="modalOpen" x-cloak>
            <div class="fixed inset-0 bg-slate-950/50" x-on:click="closeModal()"></div>

            <div class="relative mx-auto mt-10 w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl">
                <form method="POST" x-bind:action="formAction()" x-on:submit="confirmSubmit($event)">
                    @csrf
                    <input type="hidden" name="_method" value="PUT" x-bind:disabled="!isEditing()">
                    <input type="hidden" name="subject_id" x-bind:value="form.id || ''">
                    <input type="hidden" name="active" value="0">

                    <div class="border-b border-slate-200 px-6 py-5">
                        <p class="text-sm font-medium text-teal-700">Minhas materias</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-950" x-text="isEditing() ? 'Editar materia' : 'Nova materia'">Nova materia</h2>
                    </div>

                    <div class="space-y-5 px-6 py-5">
                        <div>
                            <label for="name" class="text-sm font-semibold text-slate-900">Nome</label>
                            <input id="name" name="name" type="text" maxlength="80" x-model="form.name" class="mt-2 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" autocomplete="off">
                            @error('name')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="color" class="text-sm font-semibold text-slate-900">Cor</label>
                            <div class="mt-2 flex items-center gap-3">
                                <input id="color" type="color" x-model="form.color" class="h-10 w-14 cursor-pointer rounded-md border border-slate-300 bg-white p-1">
                                <input type="text" name="color" x-model="form.color" class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" autocomplete="off">
                            </div>
                            @error('color')
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4">
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">Materia ativa</span>
                                <span class="block text-sm text-slate-500">Disponivel para novos registros.</span>
                            </span>
                            <input type="checkbox" name="active" value="1" x-model="form.active" class="rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500">
                        </label>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                        <button type="button" x-on:click="closeModal()" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
