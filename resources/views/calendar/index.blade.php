<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-medium text-teal-700">Calendario mensal</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950">{{ $calendar['label'] }}</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('calendar.index', ['month' => $calendar['previous_month']['month'], 'year' => $calendar['previous_month']['year']]) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                    &lt; {{ $calendar['previous_month']['label'] }}
                </a>
                <a href="{{ route('calendar.index', $calendar['current_month']) }}" class="inline-flex items-center justify-center rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-sm font-semibold text-teal-800 transition hover:bg-teal-100 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                    Mes atual
                </a>
                <a href="{{ route('calendar.index', ['month' => $calendar['next_month']['month'], 'year' => $calendar['next_month']['year']]) }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                    {{ $calendar['next_month']['label'] }} &gt;
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $calendarDays = collect($calendar['weeks'])
            ->flatten(1)
            ->filter()
            ->keyBy('date')
            ->all();
    @endphp

    <div
        class="space-y-6"
        x-data="studyCalendar({
            days: @js($calendarDays),
            summary: @js($calendar['summary']),
            month: @js($calendar['month']),
            year: @js($calendar['year']),
            today: @js($calendar['today']->toDateString()),
        })"
        x-on:study-record-saved.window="updateDay($event)"
    >
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-teal-700">{{ $calendar['label'] }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-950">Resumo do mes</h2>
                </div>

                <form method="GET" action="{{ route('calendar.index') }}" class="grid grid-cols-[minmax(0,1fr)_6.5rem_auto] gap-2 sm:flex sm:items-end">
                    <label class="min-w-0">
                        <span class="text-xs font-semibold uppercase text-slate-500">Mes</span>
                        <select name="month" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            @for ($monthOption = 1; $monthOption <= 12; $monthOption++)
                                <option value="{{ $monthOption }}" @selected($calendar['month'] === $monthOption)>
                                    {{ [
                                        1 => 'Janeiro',
                                        2 => 'Fevereiro',
                                        3 => 'Marco',
                                        4 => 'Abril',
                                        5 => 'Maio',
                                        6 => 'Junho',
                                        7 => 'Julho',
                                        8 => 'Agosto',
                                        9 => 'Setembro',
                                        10 => 'Outubro',
                                        11 => 'Novembro',
                                        12 => 'Dezembro',
                                    ][$monthOption] }}
                                </option>
                            @endfor
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-semibold uppercase text-slate-500">Ano</span>
                        <input type="number" name="year" min="1970" max="2100" value="{{ $calendar['year'] }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </label>
                    <button type="submit" class="mt-5 inline-flex h-10 items-center justify-center rounded-md bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 sm:mt-0">
                        Ir
                    </button>
                </form>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-md bg-emerald-50 p-4">
                    <p class="text-sm font-medium text-emerald-700">Dias estudados</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-950" x-text="summary.studied_days">{{ $calendar['summary']['studied_days'] }}</p>
                </div>
                <div class="rounded-md bg-rose-50 p-4">
                    <p class="text-sm font-medium text-rose-700">Dias nao estudados</p>
                    <p class="mt-2 text-2xl font-semibold text-rose-950" x-text="summary.not_studied_days">{{ $calendar['summary']['not_studied_days'] }}</p>
                </div>
                <div class="rounded-md bg-slate-100 p-4">
                    <p class="text-sm font-medium text-slate-600">Dias sem registro</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950" x-text="summary.unregistered_days">{{ $calendar['summary']['unregistered_days'] }}</p>
                </div>
                <div class="rounded-md bg-sky-50 p-4">
                    <p class="text-sm font-medium text-sky-700">Horas estudadas</p>
                    <p class="mt-2 text-2xl font-semibold text-sky-950"><span x-text="summary.total_hours">{{ $calendar['summary']['total_hours'] }}</span>h</p>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm sm:p-5">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-600">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Estudou</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span> Nao estudou</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border border-slate-400 bg-white"></span> Nao registrado</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border-2 border-teal-500 bg-teal-50"></span> Hoje</span>
                </div>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-[0.68rem] font-semibold uppercase text-slate-500 sm:gap-2 sm:text-xs">
                @foreach ($calendar['weekday_labels'] as $weekday)
                    <div class="py-2">{{ $weekday }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-1 sm:gap-2">
                @foreach ($calendar['weeks'] as $week)
                    @foreach ($week as $day)
                        @if ($day === null)
                            <div class="calendar-day-empty min-h-[4.25rem] rounded-md border border-dashed border-slate-100 bg-slate-50/60 sm:min-h-[8.5rem]"></div>
                        @else
                            <button
                                type="button"
                                @disabled($day['is_future'])
                                title="{{ $day['is_future'] ? 'Ainda nao e possivel registrar esta data.' : '' }}"
                                aria-label="{{ $day['aria_label'] }}"
                                data-date="{{ $day['date'] }}"
                                x-bind:data-status="day('{{ $day['date'] }}').status"
                                x-bind:data-today="day('{{ $day['date'] }}').is_today"
                                x-bind:data-future="day('{{ $day['date'] }}').is_future"
                                x-bind:aria-label="day('{{ $day['date'] }}').aria_label"
                                x-on:click="!day('{{ $day['date'] }}').is_future && $dispatch('open-study-record', { date: day('{{ $day['date'] }}').date, dateLabel: day('{{ $day['date'] }}').date_label, record: day('{{ $day['date'] }}').record })"
                                class="calendar-day group relative flex min-h-[4.25rem] flex-col rounded-md border p-1.5 text-left transition focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:cursor-not-allowed sm:min-h-[8.5rem] sm:p-3"
                            >
                                <span class="flex items-start justify-between gap-1">
                                    <span class="calendar-day-number text-sm font-semibold sm:text-base" x-text="day('{{ $day['date'] }}').day">{{ $day['day'] }}</span>
                                    <span class="calendar-today-label rounded-full px-1.5 py-0.5 text-[0.6rem] font-bold uppercase leading-none text-teal-700 sm:text-[0.65rem]" x-show="day('{{ $day['date'] }}').is_today" x-cloak>Hoje</span>
                                </span>

                                <span class="mt-auto flex items-center gap-1.5 sm:mt-4">
                                    <span class="calendar-status-dot h-2.5 w-2.5 shrink-0 rounded-full"></span>
                                    <span class="calendar-status-label hidden min-w-0 truncate text-xs font-semibold sm:block" x-text="day('{{ $day['date'] }}').status_label">{{ $day['status_label'] }}</span>
                                </span>

                                <span class="mt-2 hidden min-w-0 space-y-1 sm:block">
                                    <span class="block truncate text-xs font-medium text-slate-700" x-text="day('{{ $day['date'] }}').subject_name || 'Sem materia'">{{ $day['subject_name'] ?: 'Sem materia' }}</span>
                                    <span class="block truncate text-xs text-slate-500" x-show="day('{{ $day['date'] }}').content" x-text="day('{{ $day['date'] }}').content">{{ $day['content'] ?? '' }}</span>
                                    <span class="block text-xs text-slate-500" x-text="day('{{ $day['date'] }}').duration_label || 'Sem tempo'">{{ $day['duration_label'] ?: 'Sem tempo' }}</span>
                                </span>
                            </button>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </section>

        <x-study-record-panel :date="$today" :record="$todayRecord" :subjects="$subjects" mode="modal" />
    </div>
</x-app-layout>
