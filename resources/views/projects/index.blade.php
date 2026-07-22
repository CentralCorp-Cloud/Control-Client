@extends('layouts.dashboard')
@section('content')
<x-page-header title="Mes CentralPanel" description="Consultez vos instances, leur disponibilité et les ressources associées."><x-slot:actions><x-button href="{{ route('projects.create') }}">Commander</x-button></x-slot:actions></x-page-header>
@if($projects->isNotEmpty())
<div class="surface divider-list">
@foreach($projects as $project)
    <article class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:p-5">
        <div class="flex min-w-0 items-center gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-icon name="server"/></span><div class="min-w-0"><a class="font-semibold hover:text-brand-700 dark:hover:text-brand-300" href="{{ route('projects.show',$project->uuid) }}">{{ $project->name }}</a><p class="mt-0.5 truncate text-sm text-slate-500 dark:text-slate-400">{{ $project->publicHostname() ?? 'Adresse en préparation' }} · {{ $project->plan->name }}</p></div></div>
        <x-status-badge :status="$project->status"/>
        <x-button variant="secondary" href="{{ route('projects.show',$project->uuid) }}">Gérer</x-button>
    </article>
@endforeach
</div>
@else<x-empty-state title="Aucun CentralPanel" description="Votre première instance apparaîtra ici avec son état de déploiement."><x-button href="{{ route('projects.create') }}">Commander maintenant</x-button></x-empty-state>@endif
<div class="mt-6">{{ $projects->links() }}</div>
@endsection
