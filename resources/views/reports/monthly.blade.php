<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-teal-700">Relatorio mensal</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950">Resumo do mes</h1>
        </div>
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-metric-card label="Total considerado" :value="$monthSummary['elapsed_days'].' dias'" />
        <x-metric-card label="Dias estudados" :value="$monthSummary['studied_days'].' dias'" />
        <x-metric-card label="Tempo total" :value="$monthSummary['total_hours'].'h'" />
        <x-metric-card label="Consistencia" :value="$monthSummary['consistency'].'%'" />
    </div>
</x-app-layout>
