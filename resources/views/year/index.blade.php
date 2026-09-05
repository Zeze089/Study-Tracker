<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-teal-700">Visao anual</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950">Ano</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metric-card label="Dias estudados" :value="$yearSummary['studied_days'].' dias'" />
        <x-metric-card label="Horas estudadas" :value="$yearSummary['total_hours'].'h'" />
        <x-metric-card label="Sequencia atual" :value="$currentStreak.' dias'" />
        <x-metric-card label="Maior sequencia" :value="$longestStreak.' dias'" />
    </div>
</x-app-layout>
