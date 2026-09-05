@props(['label', 'value', 'caption' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white p-5 shadow-sm']) }}>
    <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
    <p class="mt-3 text-3xl font-semibold leading-tight text-slate-950">{{ $value }}</p>
    @if ($caption)
        <p class="mt-2 text-sm text-slate-500">{{ $caption }}</p>
    @endif
</div>
