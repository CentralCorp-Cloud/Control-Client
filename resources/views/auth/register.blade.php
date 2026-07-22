@extends('layouts.auth')
@section('content')
<div><p class="eyebrow">Nouveau compte</p><h1 class="page-title mt-2">Créer votre espace</h1><p class="page-description">Centralisez le déploiement et le suivi de vos instances.</p></div>
<form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5">@csrf
    <x-input label="Nom" name="name" required autocomplete="name"/>
    <x-input label="Adresse email" name="email" type="email" required autocomplete="email"/>
    <x-input label="Mot de passe" name="password" type="password" help="12 caractères minimum recommandés." required autocomplete="new-password"/>
    <x-input label="Confirmer le mot de passe" name="password_confirmation" type="password" required autocomplete="new-password"/>
    <x-button class="w-full">Créer mon compte</x-button>
</form>
<p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">Déjà inscrit ? <a class="font-medium text-brand-700 hover:underline dark:text-brand-300" href="{{ route('login') }}">Se connecter</a></p>
@endsection
