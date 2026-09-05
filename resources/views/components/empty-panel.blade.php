@props(['title', 'body'])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-slate-300 bg-white p-6']) }}>
    <p class="text-sm font-semibold text-slate-900">{{ $title }}</p>
    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $body }}</p>
</div>
