@extends('layouts.admin')

@section('content')
@php($ramUsed=$node->memory_total_bytes?100*(1-$node->memory_available_bytes/$node->memory_total_bytes):0)
@php($diskUsed=$node->disk_total_bytes?100*(1-$node->disk_available_bytes/$node->disk_total_bytes):0)
<div class="mb-6 flex justify-between"><div><h1 class="text-2xl font-bold">{{ $node->name }}</h1><p class="text-slate-500">Agent {{ $node->agent_node_id }}</p></div><x-status-badge :status="$node->status"/></div>
<div class="grid gap-6 lg:grid-cols-3">
    <x-card title="Mémoire"><div class="mb-2 flex justify-between text-sm"><span>{{ round(($node->memory_total_bytes-$node->memory_available_bytes)/1073741824,1) }} Gio utilisés</span><span>{{ round($node->memory_total_bytes/1073741824,1) }} Gio</span></div><div class="h-2 rounded bg-slate-200 dark:bg-slate-700"><div class="h-2 rounded bg-brand-600" style="width:{{ min(100,max(0,$ramUsed)) }}%"></div></div></x-card>
    <x-card title="Disque"><div class="mb-2 flex justify-between text-sm"><span>{{ round($node->disk_available_bytes/1073741824,1) }} Gio libres</span><span>{{ round($node->disk_total_bytes/1073741824,1) }} Gio</span></div><div class="h-2 rounded bg-slate-200 dark:bg-slate-700"><div class="h-2 rounded bg-brand-600" style="width:{{ min(100,max(0,$diskUsed)) }}%"></div></div></x-card>
    <x-stat-card label="Deployments actifs / total" :value="$node->active_deployment_count.' / '.$node->deployment_count"/>
</div>
<x-card class="mt-6" title="Identité et santé"><dl class="grid gap-3 text-sm sm:grid-cols-4"><div><dt class="text-slate-500">Version Agent</dt><dd>{{ $node->agent_version ?? '—' }}</dd></div><div><dt class="text-slate-500">Capabilities</dt><dd>{{ implode(', ', $node->capabilities ?? []) ?: 'Aucune' }}</dd></div><div><dt class="text-slate-500">CPU</dt><dd>{{ $node->cpu_count }} cœurs</dd></div><div><dt class="text-slate-500">Dernier heartbeat</dt><dd>{{ $node->last_seen_at?->diffForHumans() ?? 'Jamais' }}</dd></div></dl></x-card>
<x-card class="mt-6" title="Actions"><div class="flex flex-wrap gap-3">
    <form method="POST" action="{{ route('admin.nodes.update',$node->uuid) }}">@csrf @method('PUT')<input type="hidden" name="refresh" value="1"><x-button variant="secondary">Tester la connexion</x-button></form>
    <form method="POST" action="{{ route('admin.nodes.update',$node->uuid) }}">@csrf @method('PUT')<input type="hidden" name="scheduling_enabled" value="{{ $node->scheduling_enabled?0:1 }}"><x-button>{{ $node->scheduling_enabled?'Désactiver':'Activer' }} scheduling</x-button></form>
    <form method="POST" action="{{ route('admin.nodes.update',$node->uuid) }}">@csrf @method('PUT')<input type="hidden" name="maintenance" value="{{ $node->maintenance?0:1 }}"><x-button-danger>{{ $node->maintenance?'Quitter la maintenance':'Mode maintenance' }}</x-button-danger></form>
</div></x-card>
<x-card class="mt-6" title="Deployments">@forelse($node->deployments as $d)<div class="flex justify-between border-b py-3 dark:border-slate-800"><a class="text-brand-600" href="{{ route('admin.projects.show',$d->project->uuid) }}">{{ $d->project->name }}</a><x-status-badge :status="$d->state"/></div>@empty<p class="text-slate-500">Aucun deployment.</p>@endforelse</x-card>
@endsection
