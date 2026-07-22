@extends('layouts.dashboard')
@section('content')
<div class="mx-auto max-w-3xl"><x-page-header title="Sécurité" description="Renforcez l’accès à votre compte et contrôlez les sessions connectées."/>
<div class="space-y-6"><x-card title="Double authentification" description="Ajoutez une vérification à usage unique après votre mot de passe.">
@if(auth()->user()->hasEnabledTwoFactorAuthentication())
    <x-alert type="success">La double authentification est active sur votre compte.</x-alert><form method="POST" action="{{ url('/user/two-factor-authentication') }}">@csrf @method('DELETE')<x-button-danger>Désactiver la double authentification</x-button-danger></form>
@elseif(auth()->user()->two_factor_secret)
    <p class="mb-5 text-sm leading-6 text-slate-600 dark:text-slate-400">Scannez ce QR code avec votre application d’authentification, puis saisissez le code généré.</p><div class="mb-5 inline-block rounded-lg border border-slate-200 bg-white p-4">{!! auth()->user()->twoFactorQrCodeSvg() !!}</div><form method="POST" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="max-w-sm space-y-4">@csrf<x-input label="Code à 6 chiffres" name="code" inputmode="numeric" autocomplete="one-time-code" required/><x-button>Confirmer l’activation</x-button></form>
@else
    @if(auth()->user()->isAdministrator())<x-alert type="warning">La double authentification est obligatoire pour accéder à l’administration.</x-alert>@endif<form method="POST" action="{{ url('/user/two-factor-authentication') }}">@csrf<x-button>Activer la double authentification</x-button></form>
@endif
</x-card>
<x-card title="Sessions actives" description="Révoquez les appareils ou navigateurs que vous ne reconnaissez plus."><div class="divider-list">@forelse($sessions as $session)<div class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><p class="text-sm font-medium">{{ $session->ip_address ?? 'Adresse IP inconnue' }}</p><p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $session->user_agent }}</p></div><form method="POST" action="{{ route('security.sessions.destroy',$session->id) }}">@csrf @method('DELETE')<x-button variant="secondary">Révoquer</x-button></form></div>@empty<p class="text-sm text-slate-500">Aucune session enregistrée.</p>@endforelse</div></x-card></div></div>
@endsection
