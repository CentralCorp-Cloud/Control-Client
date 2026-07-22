@props(['label','value', 'tone'=>'default'])
<x-card class="min-h-32">
    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
    <p class="mt-3 text-3xl font-semibold tabular-nums tracking-tight {{ $tone === 'danger' ? 'text-red-700 dark:text-red-300' : 'text-slate-950 dark:text-white' }}">{{ $value }}</p>
    {{ $slot }}
</x-card>
