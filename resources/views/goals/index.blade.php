<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-teal-700">Consistencia</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950">Metas</h1>
        </div>
    </x-slot>

    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @forelse ($activeGoals as $progress)
            <div class="mb-5 last:mb-0">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="font-medium text-slate-800">{{ ucfirst($progress['goal']->type) }}</span>
                    <span class="text-slate-500">{{ $progress['studied_days'] }} / {{ $progress['target_days'] }} dias</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-teal-600" style="width: {{ $progress['percentage'] }}%"></div>
                </div>
            </div>
        @empty
            <x-empty-panel title="Nenhuma meta ativa" body="A estrutura de metas semanais e mensais ja esta preparada no dominio." />
        @endforelse
    </div>
</x-app-layout>
