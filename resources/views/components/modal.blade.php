@props(['name','title','description'=>null])
<div data-modal="{{ $name }}" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-[2px]">
    <div role="dialog" aria-modal="true" aria-labelledby="{{ $name }}-title" tabindex="-1" class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-2xl outline-none dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-start justify-between gap-4"><div><h2 id="{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2>@if($description)<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>@endif</div><button type="button" data-close-modal class="-mr-2 -mt-2 flex size-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-950 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Fermer"><x-icon name="x"/></button></div>
        <div class="mt-5">{{ $slot }}</div>
    </div>
</div>
