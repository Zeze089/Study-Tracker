<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Cronograma semanal</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $schedule['label'] }}</p>
            </div>
            <a href="{{ route('schedule.index', ['week' => $schedule['starts_on']]) }}#session-form" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Nova sessao</a>
        </div>
    </x-slot>

    @if (session('status'))
        <p role="status" class="mb-5 border-l-4 border-emerald-600 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    @if ($errors->execution->any())
        <div role="alert" class="mb-4 border-l-4 border-rose-600 bg-rose-50 p-3 text-sm text-rose-800">
            <ul class="list-inside list-disc">@foreach ($errors->execution->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($errors->reschedule->any())
        <div role="alert" class="mb-4 border-l-4 border-rose-600 bg-rose-50 p-3 text-sm text-rose-800">
            <ul class="list-inside list-disc">@foreach ($errors->reschedule->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <section aria-label="Resumo do planejamento" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="border-l-4 border-teal-600 pl-4"><p class="text-sm text-slate-600">Sessoes planejadas</p><p class="mt-2 text-xl font-semibold text-slate-950">{{ $schedule['active_count'] }}</p></div>
        <div class="border-l-4 border-sky-600 pl-4"><p class="text-sm text-slate-600">Tempo previsto</p><p class="mt-2 text-xl font-semibold text-slate-950">{{ $schedule['planned_duration'] }}</p></div>
        <div class="border-l-4 border-slate-400 pl-4"><p class="text-sm text-slate-600">Sessoes canceladas</p><p class="mt-2 text-xl font-semibold text-slate-950">{{ $schedule['cancelled_count'] }}</p></div>
        <div class="border-l-4 border-amber-600 pl-4"><p class="text-sm text-slate-600">Sessoes em atraso</p><p class="mt-2 text-xl font-semibold text-slate-950">{{ $schedule['overdue_count'] }}</p></div>
    </section>

    <nav aria-label="Navegacao entre semanas" class="mb-6 flex flex-wrap items-center gap-3">
        <a rel="prev" href="{{ route('schedule.index', ['week' => $schedule['previous_week']]) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-600">Semana anterior</a>
        <a href="{{ route('schedule.index', ['week' => $schedule['current_week']]) }}" class="rounded-md border border-teal-200 px-3 py-2 text-sm font-semibold text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600">Semana atual</a>
        <a rel="next" href="{{ route('schedule.index', ['week' => $schedule['next_week']]) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-600">Proxima semana</a>
        <form method="GET" action="{{ route('schedule.index') }}" class="flex flex-wrap items-end gap-2">
            <label for="week" class="text-sm text-slate-700">Data da semana
                <input id="week" name="week" type="date" required value="{{ $schedule['starts_on'] }}" class="mt-1 block min-w-0 rounded-md border-slate-300 text-sm focus:ring-teal-600">
            </label>
            <button type="submit" class="rounded-md bg-slate-800 px-3 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-teal-600">Abrir</button>
        </form>
    </nav>

    <section aria-label="Agenda semanal" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7">
        @foreach ($schedule['days'] as $day)
            <section aria-labelledby="day-{{ $day['date'] }}" class="min-w-0 border-t-2 pt-3 {{ $day['is_today'] ? 'border-teal-600' : 'border-slate-200' }}">
                <div class="mb-3">
                    <h2 id="day-{{ $day['date'] }}" class="text-sm font-semibold text-slate-950">{{ $day['label'] }} <span class="font-normal text-slate-600">{{ $day['date_label'] }}</span></h2>
                    <p class="mt-1 text-xs text-slate-500">{{ $day['duration_label'] }} previstos @if ($day['is_today']) · Hoje @endif</p>
                </div>
                <div class="flex flex-col gap-3">
                    @forelse ($day['sessions'] as $plannedSession)
                        <article class="min-w-0 rounded-lg border border-slate-200 bg-white p-3">
                            <p class="break-words text-sm font-semibold text-slate-900">{{ $plannedSession->subject->name }}</p>
                            <p class="mt-2 break-words text-sm text-slate-600">{{ $plannedSession->content }}</p>
                            <p class="mt-3 text-sm font-medium text-slate-800">{{ $plannedSession->scheduled_time ? substr($plannedSession->scheduled_time, 0, 5) : 'Sem horario' }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $plannedSession->planned_minutes }} min previstos</p>
                            <p class="mt-2 text-xs font-semibold {{ $plannedSession->priority === 'high' ? 'text-rose-700' : 'text-slate-600' }}">Prioridade {{ ['low' => 'baixa', 'normal' => 'normal', 'high' => 'alta'][$plannedSession->priority] }}</p>
                            <p class="mt-2 text-xs font-semibold {{ $plannedSession->cancelled_at ? 'text-slate-500' : 'text-teal-800' }}">{{ $plannedSession->execution_status }}</p>
                            @if ($plannedSession->is_overdue)
                                <p class="mt-2 text-xs font-semibold text-amber-800" role="status">Em atraso{{ $plannedSession->delay_days > 0 ? ' ha '.$plannedSession->delay_days.' dias' : ' desde o horario previsto' }}</p>
                            @endif
                            <p class="mt-2 text-xs text-slate-700">{{ $plannedSession->real_duration }} realizados</p>
                            <div role="progressbar" aria-label="Execucao da sessao {{ $plannedSession->id }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $plannedSession->execution_percentage }}" aria-valuetext="{{ $plannedSession->real_minutes }} minutos realizados de {{ $plannedSession->planned_minutes }} minutos previstos" class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full bg-teal-600" style="width: {{ $plannedSession->execution_percentage }}%"></div>
                            </div>
                            @can('execute', $plannedSession)
                                @php($retryExecution = (int) old('execution_session_id') === $plannedSession->id)
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-xs font-semibold text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600">Registrar execucao</summary>
                                    <form method="POST" action="{{ route('schedule.execute', $plannedSession) }}" class="mt-3 flex flex-col gap-3">
                                        @csrf
                                        <input type="hidden" name="execution_session_id" value="{{ $plannedSession->id }}">
                                        <input type="hidden" name="submission_key" value="{{ $retryExecution ? old('submission_key') : (string) \Illuminate\Support\Str::uuid() }}">
                                        <label for="execution-date-{{ $plannedSession->id }}" class="text-xs text-slate-700">Data real
                                            <input id="execution-date-{{ $plannedSession->id }}" name="study_date" type="date" required max="{{ $schedule['today'] }}" value="{{ $retryExecution ? old('study_date') : $schedule['today'] }}" class="mt-1 block w-full min-w-0 rounded-md border-slate-300 text-xs focus:ring-teal-600">
                                        </label>
                                        <label for="execution-minutes-{{ $plannedSession->id }}" class="text-xs text-slate-700">Minutos reais
                                            <input id="execution-minutes-{{ $plannedSession->id }}" name="actual_minutes" type="number" min="1" max="1440" required value="{{ $retryExecution ? old('actual_minutes') : '' }}" class="mt-1 block w-full rounded-md border-slate-300 text-xs focus:ring-teal-600">
                                        </label>
                                        <button type="submit" class="rounded-md bg-teal-700 px-3 py-2 text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-teal-600">Salvar execucao</button>
                                    </form>
                                </details>
                            @endcan
                            @if ($plannedSession->cancelled_at)
                                <p class="mt-1 text-xs text-slate-500">Cancelada em {{ $plannedSession->cancelled_at->copy()->setTimezone($plannedSession->timezone)->format('d/m/Y') }}</p>
                            @endif
                            <div class="mt-3 flex flex-wrap gap-3">
                                @can('update', $plannedSession)
                                    <a href="{{ route('schedule.edit', $plannedSession) }}#session-form" aria-label="Editar sessao {{ $plannedSession->id }}" class="text-xs font-semibold text-teal-800 underline focus:outline-none focus:ring-2 focus:ring-teal-600">Editar</a>
                                @endcan
                                @can('cancel', $plannedSession)
                                    <form method="POST" action="{{ route('schedule.cancel', $plannedSession) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="revision" value="{{ $plannedSession->revision_token }}">
                                        <button type="submit" aria-label="Cancelar sessao {{ $plannedSession->id }}" class="text-xs font-semibold text-slate-700 underline focus:outline-none focus:ring-2 focus:ring-teal-600">Cancelar</button>
                                    </form>
                                @endcan
                            </div>
                            @can('reschedule', $plannedSession)
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-xs font-semibold text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600">Reagendar</summary>
                                    <form method="POST" action="{{ route('schedule.reschedule', $plannedSession) }}" class="mt-3 flex flex-col gap-3">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="revision" value="{{ $plannedSession->revision_token }}">
                                        <label for="reschedule-date-{{ $plannedSession->id }}" class="text-xs text-slate-700">Nova data
                                            <input id="reschedule-date-{{ $plannedSession->id }}" name="scheduled_date" type="date" required value="{{ $plannedSession->scheduled_date->toDateString() }}" class="mt-1 block w-full min-w-0 rounded-md border-slate-300 text-xs focus:ring-teal-600">
                                        </label>
                                        <label for="reschedule-time-{{ $plannedSession->id }}" class="text-xs text-slate-700">Novo horario
                                            <input id="reschedule-time-{{ $plannedSession->id }}" name="scheduled_time" type="time" value="{{ $plannedSession->scheduled_time ? substr($plannedSession->scheduled_time, 0, 5) : '' }}" class="mt-1 block w-full min-w-0 rounded-md border-slate-300 text-xs focus:ring-teal-600">
                                        </label>
                                        <label for="reschedule-reason-{{ $plannedSession->id }}" class="text-xs text-slate-700">Motivo
                                            <textarea id="reschedule-reason-{{ $plannedSession->id }}" name="reason" required maxlength="1000" rows="2" class="mt-1 block w-full rounded-md border-slate-300 text-xs focus:ring-teal-600"></textarea>
                                        </label>
                                        <button type="submit" class="rounded-md bg-teal-700 px-3 py-2 text-xs font-semibold text-white focus:outline-none focus:ring-2 focus:ring-teal-600">Salvar reagendamento</button>
                                    </form>
                                </details>
                            @endcan
                            @if ($plannedSession->changes->isNotEmpty())
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-600">Historico de mudancas</summary>
                                    <ol class="mt-2 flex flex-col gap-3 text-xs text-slate-600">
                                        @foreach ($plannedSession->changes as $change)
                                            <li class="break-words border-t border-slate-200 pt-2">
                                                <p class="font-semibold">{{ ['created' => 'Criada', 'updated' => 'Editada', 'cancelled' => 'Cancelada', 'rescheduled' => 'Reagendada'][$change->action] }}</p>
                                                <p>{{ $change->created_at->copy()->setTimezone($plannedSession->timezone)->format('d/m/Y H:i') }} · {{ $change->actor_name }}</p>
                                                @if ($change->action === 'rescheduled')
                                                    <p>{{ $change->before['scheduled_date'] }} {{ $change->before['scheduled_time'] }} → {{ $change->after['scheduled_date'] }} {{ $change->after['scheduled_time'] }}</p>
                                                @endif
                                                @if ($change->reason)<p>{{ $change->reason }}</p>@endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </details>
                            @endif
                        </article>
                    @empty
                        <p class="py-4 text-sm text-slate-500">Sem sessoes.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </section>

    <section id="session-form" aria-labelledby="form-heading" class="mt-8 border-t border-slate-200 pt-6">
        <h2 id="form-heading" class="text-lg font-semibold text-slate-950">{{ $editingSession ? 'Editar sessao planejada' : 'Nova sessao planejada' }}</h2>
        <p class="mt-1 text-sm text-slate-600">Fuso: {{ $editingSession?->timezone ?? $schedule['timezone'] }}</p>
        @if ($errors->any())
            <div role="alert" class="mt-4 border-l-4 border-rose-600 bg-rose-50 p-3 text-sm text-rose-800">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($subjects->isEmpty())
            <p class="mt-4 text-sm text-slate-600">Nenhuma materia ativa disponivel.</p>
            <a href="{{ route('subjects.index') }}" class="mt-2 inline-block text-sm font-semibold text-teal-800 underline">Cadastrar materia</a>
        @else
            <form method="POST" action="{{ $editingSession ? route('schedule.update', $editingSession) : route('schedule.store') }}" class="mt-5 grid gap-5 md:grid-cols-2">
                @csrf
                @if ($editingSession) @method('PUT') @endif
                @if ($editingSession)
                    <input type="hidden" name="revision" value="{{ old('revision', $editingSession->revision()) }}">
                @endif
                <div>
                    <label for="subject_id" class="text-sm font-semibold text-slate-900">Materia</label>
                    <select id="subject_id" name="subject_id" required class="mt-2 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600" @error('subject_id') aria-invalid="true" aria-describedby="subject-error" @enderror>
                        <option value="">Selecione</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected((string) old('subject_id', $editingSession?->subject_id) === (string) $subject->id)>{{ $subject->name }}{{ $subject->active ? '' : ' (inativa)' }}</option>
                        @endforeach
                    </select>
                    @error('subject_id') <p id="subject-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="content" class="text-sm font-semibold text-slate-900">Conteudo</label>
                    <input id="content" name="content" type="text" required maxlength="255" value="{{ old('content', $editingSession?->content) }}" class="mt-2 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600" @error('content') aria-invalid="true" aria-describedby="content-error" @enderror>
                    @error('content') <p id="content-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="scheduled_date" class="text-sm font-semibold text-slate-900">Data planejada</label>
                    <input id="scheduled_date" name="scheduled_date" type="date" required value="{{ old('scheduled_date', $editingSession?->scheduled_date?->toDateString() ?? $initialDate ?? $schedule['today']) }}" class="mt-2 block w-full min-w-0 rounded-md border-slate-300 text-sm focus:ring-teal-600" @error('scheduled_date') aria-invalid="true" aria-describedby="date-error" @enderror>
                    @error('scheduled_date') <p id="date-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="scheduled_time" class="text-sm font-semibold text-slate-900">Horario (opcional)</label>
                    <input id="scheduled_time" name="scheduled_time" type="time" value="{{ old('scheduled_time', $editingSession?->scheduled_time ? substr($editingSession->scheduled_time, 0, 5) : '') }}" class="mt-2 block w-full min-w-0 rounded-md border-slate-300 text-sm focus:ring-teal-600" @error('scheduled_time') aria-invalid="true" aria-describedby="time-error" @enderror>
                    @error('scheduled_time') <p id="time-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="priority" class="text-sm font-semibold text-slate-900">Prioridade</label>
                    <select id="priority" name="priority" required class="mt-2 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600" @error('priority') aria-invalid="true" aria-describedby="priority-error" @enderror>
                        @foreach (['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $editingSession?->priority ?? 'normal') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority') <p id="priority-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="planned_minutes" class="text-sm font-semibold text-slate-900">Minutos previstos</label>
                    <input id="planned_minutes" name="planned_minutes" type="number" min="1" max="1440" required value="{{ old('planned_minutes', $editingSession?->planned_minutes ?? 60) }}" class="mt-2 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600" @error('planned_minutes') aria-invalid="true" aria-describedby="minutes-error" @enderror>
                    @error('planned_minutes') <p id="minutes-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap items-center gap-4 md:col-span-2">
                    @if ($editingSession)
                        <label for="change_reason" class="w-full text-sm font-semibold text-slate-900">Motivo (ao mudar data ou horario)
                            <textarea id="change_reason" name="change_reason" maxlength="1000" rows="2" class="mt-2 block w-full rounded-md border-slate-300 text-sm focus:ring-teal-600">{{ old('change_reason') }}</textarea>
                        </label>
                    @endif
                    <button type="submit" class="rounded-md bg-teal-700 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">Salvar sessao</button>
                    @if ($editingSession)
                        <a href="{{ route('schedule.index', ['week' => $schedule['starts_on']]) }}" class="text-sm text-slate-700 underline">Voltar para agenda</a>
                    @endif
                </div>
            </form>
        @endif
    </section>
</x-app-layout>
