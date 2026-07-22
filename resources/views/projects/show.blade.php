@extends('layouts.dashboard')

@section('content')
@php
    $publicHostname = $project->publicHostname();
@endphp
<x-page-header :title="$project->name" :description="$publicHostname ?? 'Votre instance est en cours de préparation.'" eyebrow="CentralPanel">
    <x-slot:actions><x-status-badge :status="$project->status"/>@if($project->deployment?->state==='active' && $publicHostname)<x-button href="https://{{ $publicHostname }}" target="_blank" rel="noopener">Ouvrir <x-icon name="external"/></x-button>@endif</x-slot:actions>
</x-page-header>

@if($project->isCustomDomain() && $project->payment_confirmed_at)
    <x-card class="mb-6" title="Domaine personnalisé" description="Le domaine doit pointer vers l’adresse canonique avant le déploiement.">
        <dl class="grid gap-5 sm:grid-cols-2">
            <div><dt class="data-label">Nom</dt><dd class="data-value break-all">{{ $project->custom_hostname }}</dd></div>
            <div><dt class="data-label">État DNS</dt><dd class="mt-2">@if($project->domain_verified_at)<x-badge color="emerald">CNAME vérifié</x-badge>@else<x-badge color="amber">Vérification en attente</x-badge>@endif</dd></div>
            <div class="sm:col-span-2"><dt class="data-label">Enregistrement à créer</dt><dd class="mt-2 overflow-x-auto rounded-lg bg-slate-950 px-4 py-3 font-mono text-sm text-slate-100"><span class="text-slate-400">CNAME</span> {{ $project->custom_hostname }} <span class="text-slate-400">→</span> {{ $project->canonical_hostname }}</dd></div>
        </dl>
        @if(!$project->domain_verified_at)
            <p class="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400">Créez un CNAME direct, sans proxy DNS pendant la validation. La propagation peut prendre plusieurs minutes.</p>
            @if($project->domain_check_error)<x-alert type="warning" class="mt-4">{{ $project->domain_check_error }} @if($project->domain_last_checked_at)<span class="block text-xs">Dernier contrôle {{ $project->domain_last_checked_at->diffForHumans() }}.</span>@endif</x-alert>@endif
            <form class="mt-4" method="POST" action="{{ route('projects.domain.verify',$project->uuid) }}">@csrf<x-button variant="secondary">Vérifier maintenant</x-button></form>
        @else
            <p class="mt-4 text-sm text-emerald-700 dark:text-emerald-300">Vérifié {{ $project->domain_verified_at->diffForHumans() }}. Le domaine sera transmis au routeur comme adresse publique.</p>
        @endif
    </x-card>
@endif

@if(!$project->deployment)
    @php
        $steps=['PENDING_PAYMENT'=>1,'PENDING_DOMAIN'=>2,'PAYMENT_CONFIRMED'=>3,'PENDING_CAPACITY'=>3,'PROVISIONING'=>4,'ACTIVE'=>5,'PROVISIONING_FAILED'=>4];
        $current=$steps[$project->status->value] ?? 1;
    @endphp
    <x-card title="Préparation de votre CentralPanel" description="La page se mettra à jour à mesure que le provisioning avance.">
        <ol class="grid gap-3 sm:grid-cols-5">
            @foreach(['Paiement','Domaine','Capacité','Déploiement','Disponible'] as $index=>$step)
                <li class="rounded-lg border p-3 {{ $index+1 <= $current ? 'border-brand-200 bg-brand-50 dark:border-brand-950 dark:bg-brand-950/30' : 'border-slate-200 dark:border-slate-800' }}"><span class="text-xs font-semibold {{ $index+1 <= $current ? 'text-brand-700 dark:text-brand-300' : 'text-slate-400' }}">0{{ $index+1 }}</span><p class="mt-2 text-sm font-medium">{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="mt-5 text-sm leading-6 text-slate-500 dark:text-slate-400">La préparation peut prendre jusqu’à 15 minutes. Vous pouvez quitter cette page sans interrompre l’opération.</p>
    </x-card>
@else
    @php($d=$project->deployment)
    @if($d->hasActiveOperation())<x-alert type="info">Une opération est en cours. Les actions incompatibles sont temporairement indisponibles.</x-alert>@endif
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(300px,0.8fr)]">
        <x-card title="État de l’instance">
            <dl class="grid gap-5 sm:grid-cols-2">
                <div><dt class="data-label">Adresse publique</dt><dd class="data-value break-all">https://{{ $publicHostname }}</dd></div>
                @if($project->isCustomDomain())<div><dt class="data-label">Adresse canonique</dt><dd class="data-value break-all">{{ $d->hostname }}</dd></div>@endif
                <div><dt class="data-label">État technique</dt><dd class="mt-2"><x-status-badge :status="$d->state"/></dd></div>
                <div><dt class="data-label">Mémoire</dt><dd class="data-value tabular-nums">{{ number_format($d->memory_bytes/1048576) }} Mio</dd></div>
                <div><dt class="data-label">Processeur</dt><dd class="data-value tabular-nums">{{ $d->cpu_limit }} vCPU</dd></div>
            </dl>
            <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
                <x-button href="{{ route('deployments.logs',$d->uuid) }}" variant="secondary">Consulter les logs</x-button>
                @if(!$d->hasActiveOperation())
                    @foreach($d->state==='stopped'?['start']:($d->state==='active'?['stop','restart']:[]) as $action)
                        <form method="POST" action="{{ route('deployments.action',[$d->uuid,$action]) }}">@csrf<x-button variant="secondary">{{ ['start'=>'Démarrer','stop'=>'Arrêter','restart'=>'Redémarrer'][$action] }}</x-button></form>
                    @endforeach
                @endif
            </div>
        </x-card>
        <x-card title="Plan et identité">
            <dl class="space-y-5"><div><dt class="data-label">Plan</dt><dd class="data-value">{{ $project->plan->name }}</dd></div><div><dt class="data-label">Création</dt><dd class="data-value">{{ $project->created_at->format('d/m/Y') }}</dd></div><div><dt class="data-label">Identifiant</dt><dd class="data-value break-all font-mono text-xs">{{ $project->uuid }}</dd></div></dl>
        </x-card>
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card title="Opérations récentes" description="Les dernières demandes envoyées à votre instance.">
            <div class="divider-list">@forelse($d->operations as $op)<div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"><div><p class="text-sm font-medium">{{ ['start'=>'Démarrage','stop'=>'Arrêt','restart'=>'Redémarrage','admin_reset'=>'Réinitialisation administrateur','delete_purge'=>'Suppression définitive'][$op->type] ?? str_replace('_',' ',ucfirst($op->type)) }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $op->created_at->diffForHumans() }}</p></div><x-status-badge :status="$op->status"/></div>@empty<p class="text-sm text-slate-500">Aucune opération enregistrée.</p>@endforelse</div>
        </x-card>
        <x-card title="Réinitialiser l’administrateur" description="Remplace l’adresse email et le mot de passe du compte administrateur CentralPanel.">
            <form method="POST" action="{{ route('deployments.admin-reset',$d->uuid) }}" class="space-y-4">@csrf<x-input label="Nouvelle adresse email" name="admin_email" type="email" required/><div class="grid gap-4 sm:grid-cols-2"><x-input label="Nouveau mot de passe" name="admin_password" type="password" required minlength="12"/><x-input label="Confirmation" name="admin_password_confirmation" type="password" required minlength="12"/></div><x-button :disabled="$d->hasActiveOperation()">Réinitialiser</x-button></form>
        </x-card>
    </div>
    @if(!$d->hasActiveOperation())
        <section class="mt-8 rounded-xl border border-red-200 bg-red-50/50 p-5 dark:border-red-950 dark:bg-red-950/20 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold text-red-950 dark:text-red-100">Zone dangereuse</h2><p class="mt-1 max-w-2xl text-sm leading-6 text-red-800 dark:text-red-300">La suppression efface définitivement PostgreSQL et le stockage de cette instance.</p></div><x-button-danger type="button" data-open-modal="purge-deployment">Supprimer le CentralPanel</x-button-danger></div>
        </section>
        <x-confirm-dialog name="purge-deployment" title="Supprimer définitivement ce CentralPanel">
            <x-alert type="error" class="mb-5">Cette action est irréversible. Toutes les données de <strong>{{ $project->name }}</strong> seront supprimées.</x-alert>
            <form method="POST" action="{{ route('deployments.purge',$d->uuid) }}" class="space-y-5">@csrf @method('DELETE')<x-input label="Saisissez le nom exact du projet" name="confirmation" :help="'Saisissez « '.$project->name.' » pour confirmer.'" required autocomplete="off"/><div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><x-button variant="secondary" type="button" data-close-modal>Annuler</x-button><x-button-danger>Supprimer définitivement</x-button-danger></div></form>
        </x-confirm-dialog>
    @endif
@endif
@endsection
