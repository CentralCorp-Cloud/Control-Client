@extends('layouts.admin')

@section('content')
<x-page-header :title="$project->name" :description="$project->owner->email.' · '.$project->uuid" eyebrow="CentralPanel"><x-slot:actions><x-status-badge :status="$project->status"/></x-slot:actions></x-page-header>
<div class="grid gap-6 lg:grid-cols-3">
    <x-card title="Commercial"><p>{{ $project->plan->name }}</p><div class="mt-2"><x-status-badge :status="$project->status"/></div><dl class="mt-4 space-y-2 text-sm"><div><dt class="text-slate-500">Domaine public</dt><dd class="break-all">{{ $project->publicHostname() ?? '—' }}</dd></div><div><dt class="text-slate-500">Hostname canonique</dt><dd class="break-all">{{ $project->canonical_hostname ?? '—' }}</dd></div>@if($project->isCustomDomain())<div><dt class="text-slate-500">Vérification DNS</dt><dd>{{ $project->domain_verified_at?->format('d/m/Y H:i') ?? 'En attente' }}</dd></div>@endif</dl></x-card>
    <x-card title="Deployment" class="lg:col-span-2">
        @if($project->deployment)
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">État</dt><dd><x-status-badge :status="$project->deployment->state"/></dd></div>
                <div><dt class="text-slate-500">Node</dt><dd>{{ $project->deployment->node?->name }}</dd></div>
                <div><dt class="text-slate-500">Hostname</dt><dd>{{ $project->deployment->hostname }}</dd></div>
                <div><dt class="text-slate-500">Ressources</dt><dd>{{ round($project->deployment->memory_bytes/1048576) }} Mio / {{ $project->deployment->cpu_limit }} CPU</dd></div>
            </dl>
        @else<p class="text-slate-500">Pas encore créé.</p>@endif
    </x-card>
</div>
@if($project->deployment)
    @php($d=$project->deployment)
    @can('manage-infrastructure')
        <x-card class="mt-6" title="Actions opérateur">
            @if($d->hasActiveOperation())
                <x-alert>Une opération est en cours. Les mutations concurrentes sont désactivées.</x-alert>
            @else
                <div class="flex flex-wrap items-end gap-3">
                    @foreach(['start','stop','restart'] as $action)<form method="POST" action="{{ route('deployments.action',[$d->uuid,$action]) }}">@csrf<x-button variant="secondary">{{ ucfirst($action) }}</x-button></form>@endforeach
                    <x-button href="{{ route('deployments.logs',$d->uuid) }}" variant="secondary">Logs</x-button>
                    @if($panelVersions->isNotEmpty())<form method="POST" action="{{ route('admin.deployments.upgrade',$d->uuid) }}" class="flex items-end gap-2">@csrf<div><x-select label="Version autorisée" name="panel_version_id" required>@foreach($panelVersions as $version)<option value="{{ $version->id }}">{{ $version->version }}{{ $version->recommended?' — recommandée':'' }}</option>@endforeach</x-select></div><x-button>Mettre à niveau</x-button></form>@endif
                    <form method="POST" action="{{ route('admin.deployments.soft-delete',$d->uuid) }}">@csrf @method('DELETE')<x-button-danger>Soft delete</x-button-danger></form>
                </div>
                <form method="POST" action="{{ route('deployments.admin-reset',$d->uuid) }}" class="mt-6 grid gap-3 sm:grid-cols-3">@csrf<x-input label="Email administrateur" name="admin_email" type="email" required/><x-input label="Nouveau mot de passe" name="admin_password" type="password" minlength="12" required/><x-input label="Confirmation" name="admin_password_confirmation" type="password" minlength="12" required/><div><x-button>Réinitialiser l’administrateur</x-button></div></form>
            @endif
        </x-card>
    @endcan
    @can('purge-deployments')
        @if(!$d->hasActiveOperation())<section class="mt-6 rounded-xl border border-red-200 bg-red-50/50 p-5 dark:border-red-950 dark:bg-red-950/20"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold text-red-950 dark:text-red-100">Purge Super Admin</h2><p class="mt-1 text-sm text-red-800 dark:text-red-300">Suppression définitive du stockage et de PostgreSQL. Le jeton reste exclusivement côté backend.</p></div><x-button-danger type="button" data-open-modal="admin-purge-deployment">Purger définitivement</x-button-danger></div></section><x-confirm-dialog name="admin-purge-deployment" title="Confirmer la purge définitive"><x-alert type="error">Toutes les données de <strong>{{ $project->name }}</strong> seront supprimées de manière irréversible.</x-alert><form method="POST" action="{{ route('deployments.purge',$d->uuid) }}" class="space-y-5">@csrf @method('DELETE')<x-input label="Nom exact du projet" name="confirmation" :help="'Saisissez « '.$project->name.' » pour confirmer.'" required autocomplete="off"/><div class="flex justify-end gap-2"><x-button variant="secondary" type="button" data-close-modal>Annuler</x-button><x-button-danger>Purger définitivement</x-button-danger></div></form></x-confirm-dialog>@endif
    @endcan
    <x-card class="mt-6" title="Opérations">
        @forelse($d->operations as $op)<div class="grid gap-2 border-b py-3 text-sm sm:grid-cols-4 dark:border-slate-800"><span>{{ $op->type }}</span><x-status-badge :status="$op->status"/><span class="font-mono text-xs">{{ $op->correlation_id }}</span><span>{{ $op->created_at->format('d/m H:i') }}</span></div>@empty<p class="text-slate-500">Aucune opération.</p>@endforelse
    </x-card>
@endif
@endsection
