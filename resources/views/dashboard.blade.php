<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-teal-700">Rotina de estudos</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950">Dashboard</h1>
            </div>

            <button type="button" x-data x-on:click="$dispatch('open-study-record')" class="inline-flex items-center justify-center rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                Registrar estudo
            </button>
        </div>
    </x-slot>

    <div
        class="space-y-6"
        x-data="studyDashboard({
            stats: @js($dashboard),
            statsUrl: @js(route('dashboard.stats')),
        })"
        x-on:study-record-saved.window="refresh()"
    >
        <section class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Hoje</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <h2 class="text-2xl font-semibold text-slate-950" x-text="stats.today.date_label">{{ $dashboard['today']['date_label'] }}</h2>
                            <span class="rounded-md px-2.5 py-1 text-xs font-semibold" :class="todayStatusClasses()" x-text="todayStatusLabel()">
                                {{ $dashboard['today_record']['status_label'] ?? 'Nenhum estudo registrado ainda.' }}
                            </span>
                        </div>

                        <div class="mt-5 space-y-2" x-show="stats.today_record" x-cloak>
                            <p class="text-sm font-semibold text-slate-950" x-text="stats.today_record?.subject_name || 'Sem materia'">
                                {{ $dashboard['today_record']['subject_name'] ?? 'Sem materia' }}
                            </p>
                            <p class="truncate text-sm text-slate-500" x-show="stats.today_record?.content" x-text="stats.today_record?.content">
                                {{ $dashboard['today_record']['content'] ?? '' }}
                            </p>
                            <p class="text-sm text-slate-500" x-text="stats.today_record?.duration_label || 'Sem tempo informado'">
                                {{ $dashboard['today_record']['duration_label'] ?? 'Sem tempo informado' }}
                            </p>
                            <p class="truncate text-sm text-slate-500" x-text="stats.today_record?.notes || 'Sem observacao'">
                                {{ $dashboard['today_record']['notes'] ?? 'Sem observacao' }}
                            </p>
                        </div>

                        <p class="mt-5 text-sm text-slate-500" x-show="!stats.today_record" x-cloak>
                            Nenhum estudo registrado ainda.
                        </p>
                    </div>

                    <button
                        type="button"
                        x-on:click="$dispatch('open-study-record', { date: stats.today.date, dateLabel: stats.today.date_label, record: stats.today_record })"
                        class="inline-flex items-center justify-center rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                        x-text="buttonLabel()"
                    >
                        {{ $dashboard['today_record'] ? 'Editar registro' : 'Registrar estudo' }}
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-950 p-5 text-white shadow-sm sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-200">Materia mais estudada</p>
                <div class="mt-4" x-show="stats.top_subject" x-cloak>
                    <p class="text-2xl font-semibold" x-text="stats.top_subject?.name">{{ $dashboard['top_subject']['name'] ?? '' }}</p>
                    <p class="mt-2 text-sm text-slate-300">
                        <span x-text="stats.top_subject?.duration_label">{{ $dashboard['top_subject']['duration_label'] ?? '0min' }}</span>
                        acumulados neste mes
                    </p>
                </div>
                <div class="mt-4" x-show="!stats.top_subject">
                    <p class="text-2xl font-semibold">Sem materia ainda</p>
                    <p class="mt-2 text-sm text-slate-300">Registros sem tempo nao inventam horas para o ranking.</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm font-medium text-slate-500">Sequencia atual</p>
                    <span class="rounded-md bg-teal-50 px-2 py-1 text-xs font-bold text-teal-700">SC</span>
                </div>
                <p class="mt-3 text-3xl font-semibold leading-tight text-slate-950"><span x-text="stats.current_streak">{{ $dashboard['current_streak'] }}</span> dias</p>
                <p class="mt-2 text-sm text-slate-500">Hoje sem registro ainda nao quebra a sequencia.</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm font-medium text-slate-500">Dias estudados</p>
                    <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">DS</span>
                </div>
                <p class="mt-3 text-3xl font-semibold leading-tight text-slate-950"><span x-text="stats.month_summary.studied_days">{{ $dashboard['month_summary']['studied_days'] }}</span> dias</p>
                <p class="mt-2 text-sm text-slate-500">Neste mes, ate hoje.</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm font-medium text-slate-500">Tempo estudado</p>
                    <span class="rounded-md bg-sky-50 px-2 py-1 text-xs font-bold text-sky-700">TE</span>
                </div>
                <p class="mt-3 text-3xl font-semibold leading-tight text-slate-950" x-text="stats.month_summary.total_hours_label">{{ $dashboard['month_summary']['total_hours_label'] }}</p>
                <p class="mt-2 text-sm text-slate-500">Minutos informados no mes.</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm font-medium text-slate-500">Consistencia</p>
                    <span class="rounded-md bg-violet-50 px-2 py-1 text-xs font-bold text-violet-700">%</span>
                </div>
                <p class="mt-3 text-3xl font-semibold leading-tight text-slate-950"><span x-text="stats.month_summary.consistency">{{ $dashboard['month_summary']['consistency'] }}</span>%</p>
                <p class="mt-2 text-sm text-slate-500"><span x-text="stats.month_summary.studied_days">{{ $dashboard['month_summary']['studied_days'] }}</span> de <span x-text="stats.month_summary.elapsed_days">{{ $dashboard['month_summary']['elapsed_days'] }}</span> dias decorridos.</p>
            </div>
        </section>

        <section x-show="!stats.has_records" class="rounded-lg border border-dashed border-teal-200 bg-teal-50 p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Voce ainda nao possui registros de estudo.</h2>
                    <p class="mt-1 text-sm text-slate-600">Comece registrando seu primeiro dia.</p>
                </div>
                <button type="button" x-on:click="$dispatch('open-study-record', { date: stats.today.date, dateLabel: stats.today.date_label, record: stats.today_record })" class="inline-flex items-center justify-center rounded-md bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                    Registrar estudo de hoje
                </button>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-teal-700" x-text="stats.month_label">{{ $dashboard['month_label'] }}</p>
                        <h2 class="mt-1 text-base font-semibold text-slate-950">Consistencia do mes</h2>
                    </div>
                    <span class="rounded-md bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">
                        <span x-text="stats.longest_streak">{{ $dashboard['longest_streak'] }}</span> dias na maior sequencia
                    </span>
                </div>

                <div class="mt-6">
                    <div class="flex items-end justify-between gap-4">
                        <p class="text-4xl font-semibold text-slate-950"><span x-text="stats.month_summary.consistency">{{ $dashboard['month_summary']['consistency'] }}</span>%</p>
                        <p class="text-right text-sm text-slate-500"><span x-text="stats.month_summary.registered_days">{{ $dashboard['month_summary']['registered_days'] }}</span> registros em <span x-text="stats.month_summary.elapsed_days">{{ $dashboard['month_summary']['elapsed_days'] }}</span> dias</p>
                    </div>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-teal-600 transition-all" :style="`width: ${stats.month_summary.consistency}%`" style="width: {{ $dashboard['month_summary']['consistency'] }}%"></div>
                    </div>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-md bg-emerald-50 p-4">
                        <p class="text-sm font-medium text-emerald-700">Estudou</p>
                        <p class="mt-2 text-2xl font-semibold text-emerald-950" x-text="stats.month_summary.studied_days">{{ $dashboard['month_summary']['studied_days'] }}</p>
                    </div>
                    <div class="rounded-md bg-rose-50 p-4">
                        <p class="text-sm font-medium text-rose-700">Nao estudou</p>
                        <p class="mt-2 text-2xl font-semibold text-rose-950" x-text="stats.month_summary.not_studied_days">{{ $dashboard['month_summary']['not_studied_days'] }}</p>
                    </div>
                    <div class="rounded-md bg-slate-100 p-4">
                        <p class="text-sm font-medium text-slate-600">Sem registro</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-950" x-text="stats.month_summary.unregistered_days">{{ $dashboard['month_summary']['unregistered_days'] }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Minutos estudados nos ultimos 7 dias</h2>
                        <p class="mt-1 text-sm text-slate-500">Dias sem estudo aparecem como zero.</p>
                    </div>
                    <p class="rounded-md bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700" x-show="loading" x-cloak>Atualizando</p>
                </div>
                <div class="mt-6 h-72">
                    <canvas x-ref="recentChart" aria-label="Grafico de minutos estudados nos ultimos 7 dias"></canvas>
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Materias neste mes</h2>
                        <p class="mt-1 text-sm text-slate-500">Ranking por minutos acumulados; registros sem materia entram como categoria propria.</p>
                    </div>
                    <a href="{{ route('subjects.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">Gerenciar</a>
                </div>

                <div class="mt-6 space-y-3" x-show="stats.top_subjects.length > 0" x-cloak>
                    <template x-for="subject in stats.top_subjects" :key="subject.id || 'empty-subject'">
                        <div class="flex items-center justify-between gap-4 rounded-md border border-slate-200 p-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="h-3 w-3 shrink-0 rounded-full" :style="`background-color: ${subject.color}`"></span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900" x-text="subject.name"></p>
                                    <p class="text-xs text-slate-500"><span x-text="subject.records_count"></span> dias registrados</p>
                                </div>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-slate-700" x-text="subject.duration_label"></span>
                        </div>
                    </template>
                </div>

                <x-empty-panel title="Sem materias registradas" body="Os proximos registros com estudo alimentarao este ranking automaticamente." class="mt-6" x-show="stats.top_subjects.length === 0" />
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-semibold text-slate-950">Atividade recente</h2>
                <p class="mt-1 text-sm text-slate-500">Ultimos registros, no maximo cinco.</p>

                <div class="mt-6 space-y-3" x-show="stats.recent_activity.length > 0" x-cloak>
                    <template x-for="activity in stats.recent_activity" :key="activity.id">
                        <div class="rounded-md border border-slate-200 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-950" x-text="activity.date_label"></p>
                                        <p class="mt-1 truncate text-sm text-slate-600" x-text="activity.studied ? activity.subject_name : 'Nao estudou'"></p>
                                        <p class="mt-1 truncate text-sm text-slate-500" x-show="activity.content" x-text="activity.content"></p>
                                    </div>
                                    <span class="rounded-md px-2.5 py-1 text-xs font-semibold" :class="activity.studied ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'" x-text="activity.studied ? activity.duration_label : activity.status_label"></span>
                                </div>
                        </div>
                    </template>
                </div>

                <x-empty-panel title="Sem atividade recente" body="Quando houver registros, eles aparecerao aqui." class="mt-6" x-show="stats.recent_activity.length === 0" />
            </div>
        </section>

        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700" x-show="errorMessage" x-text="errorMessage" x-cloak></p>

        <x-study-record-panel :date="$today" :record="$todayRecord" :subjects="$subjects" mode="modal" />
    </div>
</x-app-layout>
