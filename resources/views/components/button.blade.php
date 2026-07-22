@props(['href'=>null,'variant'=>'primary','type'=>'submit'])
@php
    $classes = [
        'primary' => 'border border-slate-900 bg-slate-900 text-white hover:bg-slate-800 dark:border-white dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800',
        'ghost' => 'border border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
        'danger' => 'border border-red-700 bg-red-700 text-white hover:bg-red-800 dark:border-red-600 dark:bg-red-600 dark:hover:bg-red-500',
    ][$variant] ?? '';
    $base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold shadow-xs transition-colors disabled:pointer-events-none disabled:opacity-50';
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class'=>$base.' '.$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class'=>$base.' '.$classes]) }}><span data-submit-spinner class="submit-spinner" aria-hidden="true"></span>{{ $slot }}</button>
@endif
