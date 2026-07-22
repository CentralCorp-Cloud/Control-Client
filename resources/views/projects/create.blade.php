@extends('layouts.dashboard')
@section('content')
<div class="mx-auto max-w-3xl">
<x-page-header title="Créer un CentralPanel" description="Choisissez les ressources de l’instance et définissez son premier compte administrateur."/>
<form method="POST" action="{{ route('projects.store') }}" class="space-y-6" data-project-domain-form data-domain-suffix="{{ config('centralcloud.panel.domain_suffix') }}">@csrf
    <x-card title="Instance" description="Le nom vous permettra d’identifier ce CentralPanel dans votre espace.">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-input label="Nom du projet" name="name" required autocomplete="organization" data-project-name/>
            <x-select label="Plan" name="plan_id" required data-plan-select>
                @foreach($plans as $plan)<option value="{{ $plan->id }}" data-is-free="{{ $plan->is_free ? 'true' : 'false' }}" @selected(old('plan_id',$plans->first()?->id)==$plan->id)>{{ $plan->name }} — {{ $plan->is_free?'Gratuit':number_format($plan->price/100,2,',',' ').' '.$plan->currency.'/'.($plan->billing_interval==='month'?'mois':'an') }}</option>@endforeach
            </x-select>
        </div>
        <p class="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400">L’offre gratuite ne demande aucune carte bancaire et est limitée à un CentralPanel par compte.</p>
    </x-card>
    <x-card title="Adresse du CentralPanel" description="Définissez l’adresse publique utilisée pour accéder à votre instance.">
        <fieldset data-domain-mode-fieldset>
            <legend class="label">Type de domaine</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-950/30">
                    <input class="mt-1 size-4 accent-brand-600" type="radio" name="domain_mode" value="CENTRALCLOUD" @checked(old('domain_mode','CENTRALCLOUD')==='CENTRALCLOUD')>
                    <span><strong class="block text-sm">Domaine CentralCloud</strong><span class="mt-1 block text-sm leading-5 text-slate-500 dark:text-slate-400">Choisissez une adresse en .{{ config('centralcloud.panel.domain_suffix') }}.</span></span>
                </label>
                <label data-custom-domain-choice class="relative flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:has-[:checked]:border-brand-500 dark:has-[:checked]:bg-brand-950/30 dark:focus-visible:ring-offset-slate-900">
                    <input class="mt-1 size-4 accent-brand-600" type="radio" name="domain_mode" value="CUSTOM" @checked(old('domain_mode')==='CUSTOM')>
                    <span class="min-w-0 flex-1"><span class="flex items-center justify-between gap-3"><strong class="block text-sm">Domaine personnalisé</strong><span data-custom-domain-lock hidden class="shrink-0 rounded-full bg-amber-100 p-1.5 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300" title="Offre payante uniquement"><x-icon name="lock" class="size-3.5"/><span class="sr-only">Verrouillé</span></span></span><span class="mt-1 block text-sm leading-5 text-slate-500 dark:text-slate-400">Disponible avec un plan payant après validation DNS.</span></span>
                </label>
            </div>
            <p data-custom-domain-locked-message hidden class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200" role="status" aria-live="polite"><span class="inline-flex items-center gap-2"><x-icon name="lock" class="size-4"/> Offre payante uniquement. Sélectionnez un plan payant pour utiliser un domaine personnalisé.</span></p>
            @error('domain_mode')<span class="mt-2 block field-error" role="alert">{{ $message }}</span>@enderror
        </fieldset>
        <div class="mt-5" data-central-domain-panel>
            <x-input label="Sous-domaine CentralCloud" name="central_subdomain" :value="old('central_subdomain')" help="Lettres minuscules, chiffres et tirets uniquement." required autocomplete="off" data-central-subdomain/>
            <p class="mt-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-slate-800/70 dark:text-slate-300" aria-live="polite">Adresse prévue : <strong data-domain-preview>—</strong></p>
        </div>
        <div class="mt-5" data-custom-domain-panel hidden>
            <x-input label="Domaine personnalisé" name="custom_url" :value="old('custom_url')" help="Exemple : panel.example.com. Les domaines racine ne sont pas pris en charge." placeholder="panel.example.com" autocomplete="url" data-custom-domain/>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Après le paiement, CentralCloud vous indiquera le CNAME à créer avant de lancer le déploiement.</p>
        </div>
    </x-card>
    <x-card title="Administrateur CentralPanel" description="Ces identifiants sont chiffrés pendant le provisioning puis supprimés."><div class="grid gap-5"><x-input label="Adresse email" name="admin_email" type="email" required autocomplete="email"/><div class="grid gap-5 sm:grid-cols-2"><x-input label="Mot de passe" name="admin_password" type="password" help="12 caractères minimum." required autocomplete="new-password" minlength="12"/><x-input label="Confirmation" name="admin_password_confirmation" type="password" required autocomplete="new-password" minlength="12"/></div></div></x-card>
    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><x-button variant="ghost" href="{{ route('projects.index') }}">Annuler</x-button><x-button>Créer ou continuer vers le paiement</x-button></div>
</form>
</div>
@endsection
