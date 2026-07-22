<div class="flex rounded-lg border border-slate-200 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-900" aria-label="Thème d’affichage">
    @foreach(['light'=>'Clair','dark'=>'Sombre','system'=>'Auto'] as $value=>$label)<button type="button" data-theme-value="{{ $value }}" class="min-h-9 rounded-md px-2.5 text-xs font-medium text-slate-500 transition-colors hover:text-slate-950 dark:text-slate-400 dark:hover:text-white" aria-pressed="false">{{ $label }}</button>@endforeach
</div>
