@extends('layouts.auth')
@section('content')
<div data-two-factor-method><p class="eyebrow">Sécurité du compte</p><h1 class="page-title mt-2">Double authentification</h1><p class="page-description">Confirmez cette connexion avec votre méthode de secours.</p>
<div class="mt-6 grid grid-cols-2 rounded-lg bg-slate-100 p-1 dark:bg-slate-800"><button type="button" data-method="totp" aria-pressed="true" class="min-h-10 rounded-md bg-white text-sm font-medium shadow-xs dark:bg-slate-900">Application</button><button type="button" data-method="recovery" aria-pressed="false" class="min-h-10 rounded-md text-sm font-medium text-slate-500 dark:text-slate-400">Code de récupération</button></div>
<form method="POST" action="{{ url('/two-factor-challenge') }}" class="mt-6 space-y-5">@csrf<div data-method-panel="totp"><x-input label="Code à 6 chiffres" name="code" inputmode="numeric" autocomplete="one-time-code"/></div><div data-method-panel="recovery" hidden><x-input label="Code de récupération" name="recovery_code" autocomplete="one-time-code"/></div><x-button class="w-full">Continuer</x-button></form></div>
@endsection
