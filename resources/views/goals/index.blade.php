<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold text-slate-950">Metas de estudo</h1>
            <a href="{{ route('goals.index') }}#goal-form" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Nova meta</a>
        </div>
    </x-slot>

    @if (session('status'))
        <p role="status" class="mb-5 border-l-4 border-emerald-600 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <section aria-label="Resumo geral de metas" class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-metric-card label="Total de metas" :value="$summary['total']" />
        <x-metric-card label="Metas atuais" :value="$summary['current']" />
        <x-metric-card label="Metas atingidas" :value="$summary['achieved']" />
        <x-metric-card label="No historico" :value="$summary['history']" />
    </section>

    <nav aria-label="Visualizacao de metas" class="mb-5 flex flex-wrap gap-4 border-b border-slate-200 pb-3">
        @foreach (['all' => 'Todas', 'current' => 'Atuais', 'history' => 'Historico'] as $key => $label)
            <a href="{{ route('goals.index', array_merge(array_diff_key($filters, ['page' => true]), ['view' => $key])) }}" @if (($filters['view'] ?? 'all') === $key) aria-current="page" @endif class="text-sm font-semibold underline-offset-8 focus:outline-none focus:ring-2 focus:ring-teal-600 {{ ($filters['view'] ?? 'all') === $key ? 'text-teal-800 underline' : 'text-slate-600' }}">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($errors->filters->any())
        <div role="alert" class="mb-4 border-l-4 border-rose-600 bg-rose-50 p-3 text-sm text-rose-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->filters->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form method="GET" action="{{ route('goals.index') }}" aria-label="Filtrar metas" class="mb-8 grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <input type="hidden" name="view" value="{{ $filters['view'] ?? 'all' }}">
        <div>
            <label for="filter-type" class="text-sm font-medium text-slate-700">Periodo</label>
            <select id="filter-type" name="type" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600">
                <option value="">Todos</option>
                @foreach (['daily' => 'Diario', 'weekly' => 'Semanal', 'monthly' => 'Mensal'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-metric" class="text-sm font-medium text-slate-700">Metrica</label>
            <select id="filter-metric" name="metric" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600">
                <option value="">Todas</option>
                <option value="days" @selected(($filters['metric'] ?? '') === 'days')>Dias estudados</option>
                <option value="minutes" @selected(($filters['metric'] ?? '') === 'minutes')>Tempo estudado</option>
            </select>
        </div>
        <div>
            <label for="filter-status" class="text-sm font-medium text-slate-700">Estado</label>
            <select id="filter-status" name="status" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600">
                <option value="">Todos</option>
                @foreach (['planned' => 'Planejada', 'ongoing' => 'Em andamento', 'achieved' => 'Atingida', 'closed' => 'Encerrada', 'archived' => 'Arquivada'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-size" class="text-sm font-medium text-slate-700">Por pagina</label>
            <select id="filter-size" name="per_page" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600">
                @foreach ([15, 30, 60] as $size)
                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 15) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="filter-from" class="text-sm font-medium text-slate-700">Periodo a partir de</label>
            <input id="filter-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full min-w-0 rounded-md border-slate-300 text-sm focus:ring-teal-600">
        </div>
        <div>
            <label for="filter-until" class="text-sm font-medium text-slate-700">Periodo ate</label>
            <input id="filter-until" name="until" type="date" value="{{ $filters['until'] ?? '' }}" class="mt-1 block w-full min-w-0 rounded-md border-slate-300 text-sm focus:ring-teal-600">
        </div>
        <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
            <button type="submit" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Filtrar</button>
            <a href="{{ route('goals.index') }}" class="text-sm font-semibold text-slate-700 underline focus:outline-none focus:ring-2 focus:ring-teal-600">Limpar filtros</a>
        </div>
    </form>

    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section aria-labelledby="goals-heading" class="min-w-0">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 id="goals-heading" class="text-lg font-semibold text-slate-900">{{ ($filters['view'] ?? 'all') === 'history' ? 'Historico de metas' : 'Minhas metas' }}</h2>
                <p role="status" class="text-sm text-slate-600">{{ $goals->total() }} metas encontradas</p>
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                @forelse ($goals as $progress)
                    @php($goal = $progress['goal'])
                    <article class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-900">{{ $progress['period_label'] }}</h3>
                                <p class="mt-1 text-sm text-slate-600">{{ $goal->starts_on->format('d/m/Y') }} a {{ $goal->ends_on->format('d/m/Y') }}</p>
                            </div>
                            <span class="rounded-md px-2 py-1 text-xs font-semibold {{ match ($progress['status']) { 'Atingida' => 'bg-emerald-50 text-emerald-800', 'Encerrada' => 'bg-amber-50 text-amber-800', 'Arquivada' => 'bg-slate-100 text-slate-700', 'Planejada' => 'bg-sky-50 text-sky-800', default => 'bg-teal-50 text-teal-800' } }}">{{ $progress['status'] }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-between gap-2 text-sm">
                            <span class="font-medium text-slate-800">{{ $progress['achieved_label'] }} / {{ $progress['target_label'] }}</span>
                            <span class="text-slate-600">{{ $progress['percentage'] }}%</span>
                        </div>
                        <div role="progressbar" aria-label="Progresso da meta {{ $goal->id }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percentage'] }}" aria-valuetext="{{ $progress['achieved_label'] }} de {{ $progress['target_label'] }}" class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full bg-teal-600" style="width: {{ $progress['percentage'] }}%"></div>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-500">Dias estudados</dt><dd class="font-semibold text-slate-900">{{ $progress['studied_days'] }}</dd></div>
                            <div><dt class="text-slate-500">Tempo estudado</dt><dd class="font-semibold text-slate-900">{{ $progress['duration_label'] }}</dd></div>
                        </dl>
                        <p class="mt-4 text-sm text-slate-600">{{ $progress['message'] }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-4">
                            @if ($progress['status'] === 'Em andamento')
                                <p class="text-sm text-slate-600">{{ $progress['days_left'] }} dias ate o fim do periodo</p>
                            @endif
                            @can('update', $goal)
                                <a href="{{ route('goals.edit', $goal) }}#goal-form" aria-label="Editar meta {{ $goal->id }}" class="text-sm font-semibold text-teal-800 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-teal-600">Editar</a>
                            @endcan
                            @can('archive', $goal)
                                <form method="POST" action="{{ route('goals.archive', $goal) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" aria-label="Arquivar meta {{ $goal->id }}" class="text-sm font-semibold text-slate-700 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-teal-600">Arquivar</button>
                                </form>
                            @endcan
                        </div>
                    </article>
                @empty
                    <p class="py-8 text-sm text-slate-600">{{ $summary['total'] === 0 ? 'Nenhuma meta cadastrada.' : 'Nenhuma meta corresponde aos filtros selecionados.' }}</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $goals->links() }}</div>
        </section>

        <section id="goal-form" aria-labelledby="form-heading" class="min-w-0 border-t border-slate-200 pt-5 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
            <h2 id="form-heading" class="text-lg font-semibold text-slate-900">{{ $editingGoal ? 'Editar meta' : 'Nova meta' }}</h2>
            @if ($errors->any())
                <div role="alert" class="mt-4 border-l-4 border-rose-600 bg-rose-50 p-3 text-sm text-rose-800">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ $editingGoal ? route('goals.update', $editingGoal) : route('goals.store') }}" class="mt-5 flex flex-col gap-5" x-data="{ metric: @js(old('metric', $editingGoal?->metric ?? 'days')) }">
                @csrf
                @if ($editingGoal)
                    @method('PUT')
                @endif
                <div>
                    <label for="type" class="text-sm font-semibold text-slate-900">Periodo</label>
                    <select id="type" name="type" required class="mt-2 w-full rounded-md border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600" @error('type') aria-invalid="true" aria-describedby="type-error" @enderror>
                        <option value="daily" @selected(old('type', $editingGoal?->type) === 'daily')>Diario</option>
                        <option value="weekly" @selected(old('type', $editingGoal?->type ?? 'weekly') === 'weekly')>Semanal</option>
                        <option value="monthly" @selected(old('type', $editingGoal?->type) === 'monthly')>Mensal</option>
                    </select>
                    @error('type') <p id="type-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="starts_on" class="text-sm font-semibold text-slate-900">Inicio</label>
                    <input id="starts_on" name="starts_on" type="date" required value="{{ old('starts_on', $editingGoal?->starts_on?->toDateString()) }}" class="mt-2 block w-full min-w-0 rounded-md border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600" @error('starts_on') aria-invalid="true" aria-describedby="starts-error" @enderror>
                    @error('starts_on') <p id="starts-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ends_on" class="text-sm font-semibold text-slate-900">Fim</label>
                    <input id="ends_on" name="ends_on" type="date" required value="{{ old('ends_on', $editingGoal?->ends_on?->toDateString()) }}" class="mt-2 block w-full min-w-0 rounded-md border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600" @error('ends_on') aria-invalid="true" aria-describedby="ends-error" @enderror>
                    @error('ends_on') <p id="ends-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="metric" class="text-sm font-semibold text-slate-900">Metrica</label>
                    <select id="metric" name="metric" x-model="metric" required class="mt-2 w-full rounded-md border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600" @error('metric') aria-invalid="true" aria-describedby="metric-error" @enderror>
                        <option value="days" @selected(old('metric', $editingGoal?->metric ?? 'days') === 'days')>Dias estudados</option>
                        <option value="minutes" @selected(old('metric', $editingGoal?->metric) === 'minutes')>Tempo estudado</option>
                    </select>
                    @error('metric') <p id="metric-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div x-show="metric === 'days'">
                    <label for="target_days" class="text-sm font-semibold text-slate-900">Dias-alvo</label>
                    <input id="target_days" name="target_days" type="number" min="1" max="31" x-bind:required="metric === 'days'" x-bind:disabled="metric !== 'days'" value="{{ old('target_days', $editingGoal?->target_days) }}" class="mt-2 w-full rounded-md border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600" @error('target_days') aria-invalid="true" aria-describedby="target-error" @enderror>
                    @error('target_days') <p id="target-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div x-show="metric === 'minutes'" x-cloak>
                    <label for="target_minutes" class="text-sm font-semibold text-slate-900">Minutos-alvo</label>
                    <input id="target_minutes" name="target_minutes" type="number" min="1" max="44640" x-bind:required="metric === 'minutes'" x-bind:disabled="metric !== 'minutes'" value="{{ old('target_minutes', $editingGoal?->target_minutes) }}" class="mt-2 w-full rounded-md border-slate-300 text-sm focus:border-teal-600 focus:ring-teal-600" @error('target_minutes') aria-invalid="true" aria-describedby="minutes-error" @enderror>
                    @error('target_minutes') <p id="minutes-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Salvar meta</button>
                    @if ($editingGoal)
                        <a href="{{ route('goals.index') }}" class="text-sm font-semibold text-slate-700 underline">Cancelar</a>
                    @endif
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
