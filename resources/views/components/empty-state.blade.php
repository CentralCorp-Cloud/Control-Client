@props(['title'=>'Aucun élément','description'=>null])
<div class="surface border-dashed px-6 py-12 text-center">
    <span class="mx-auto flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><x-icon name="server"/></span>
    <h3 class="mt-4 font-semibold text-slate-950 dark:text-white">{{ $title }}</h3>
    @if($description)<p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $description }}</p>@endif
    @if(trim((string) $slot) !== '')<div class="mt-5">{{ $slot }}</div>@endif
</div>
