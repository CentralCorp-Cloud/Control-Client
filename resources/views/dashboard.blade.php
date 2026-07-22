@extends('layouts.dashboard')
@section('content')
<x-page-header :title="'Bonjour, '.auth()->user()->name" description="Suivez l’état de vos CentralPanel et accédez rapidement aux opérations essentielles.">
    <x-slot:actions><x-button href="{{ route('projects.create') }}">Nouveau CentralPanel</x-button></x-slot:actions>
</x-page-header>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat-card label="CentralPanel" :value="$stats['total']"/>
    <x-stat-card label="Actifs" :value="$stats['active']"/>
    <x-stat-card label="En préparation" :value="$stats['provisioning']"/>
    <x-stat-card label="À vérifier" :value="$stats['failed']" :tone="$stats['failed'] ? 'danger' : 'default'"/>
</div>
<section class="mt-8">
    <div class="mb-4 flex items-center justify-between"><div><h2 class="section-title">CentralPanel récents</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Vos dernières instances et leur état actuel.</p></div>@if($projects->isNotEmpty())<a class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" href="{{ route('projects.index') }}">Tout afficher</a>@endif</div>
    @if($projects->isNotEmpty())
        <div class="surface divider-list">
            @foreach($projects as $project)
                <article class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">
                    <div class="flex min-w-0 flex-1 items-center gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-icon name="server"/></span><div class="min-w-0"><a href="{{ route('projects.show',$project->uuid) }}" class="font-semibold hover:text-brand-700 dark:hover:text-brand-300">{{ $project->name }}</a><p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $project->publicHostname() ?? $project->plan->name }}</p></div></div>
                    <x-status-badge :status="$project->status"/>
                    <div class="flex items-center gap-2"><x-button variant="secondary" href="{{ route('projects.show',$project->uuid) }}">Gérer</x-button>@if($project->deployment?->hostname && $project->publicHostname())<a class="flex size-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-950 dark:hover:bg-slate-800 dark:hover:text-white" href="https://{{ $project->publicHostname() }}" target="_blank" rel="noopener" aria-label="Ouvrir {{ $project->name }} dans un nouvel onglet"><x-icon name="external"/></a>@endif</div>
                </article>
            @endforeach
        </div>
    @else
        <x-empty-state title="Aucun CentralPanel" description="Créez votre première instance managée pour commencer."><x-button href="{{ route('projects.create') }}">Créer un CentralPanel</x-button></x-empty-state>
    @endif
</section>
@endsection
