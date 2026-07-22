@extends('layouts.dashboard')
@section('content')
<div data-logs-viewer="{{ route('deployments.logs.data', $deployment->uuid) }}">
    <x-page-header title="Logs CentralPanel" :description="$deployment->project->name"><x-slot:actions><x-button variant="secondary" data-logs-copy type="button">Copier</x-button><x-button href="{{ route('projects.show', $deployment->project->uuid) }}" variant="secondary">Retour au projet</x-button></x-slot:actions></x-page-header>
    <x-alert type="warning">Les secrets sont filtrés par l’Agent et ne sont pas conservés dans MySQL. Vérifiez néanmoins le contenu avant tout partage.</x-alert>
    <div data-logs-error hidden class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200" role="alert"></div>
    <div class="mt-4 overflow-hidden rounded-xl border border-slate-800 bg-slate-950"><div class="flex items-center justify-between border-b border-slate-800 px-4 py-3"><span class="text-xs font-medium text-slate-400">Sortie de l’Agent</span><span data-logs-loading class="text-xs text-slate-400">Chargement…</span></div><pre data-logs-output class="max-h-[65vh] min-h-80 overflow-auto p-4 text-xs leading-5 text-slate-100" tabindex="0" aria-label="Logs de l’instance"></pre></div>
    <div class="mt-4"><x-button variant="secondary" data-logs-load hidden type="button">Charger plus</x-button></div>
</div>
@endsection
