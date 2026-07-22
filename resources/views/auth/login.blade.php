@extends('layouts.auth')
@section('content')
<div><p class="eyebrow">Espace client</p><h1 class="page-title mt-2">Connexion</h1><p class="page-description">Accédez à vos CentralPanel et à leurs opérations.</p></div>
<form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">@csrf
    <x-input label="Adresse email" name="email" type="email" required autofocus autocomplete="email"/>
    <x-input label="Mot de passe" name="password" type="password" required autocomplete="current-password"/>
    <div class="flex items-center justify-between gap-4"><label class="flex min-h-11 items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input class="size-4 rounded border-slate-300 text-brand-600" type="checkbox" name="remember">Se souvenir de moi</label><a class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" href="{{ route('password.request') }}">Mot de passe oublié ?</a></div>
    <x-button class="w-full">Se connecter</x-button>
</form>
<p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">Pas encore de compte ? <a class="font-medium text-brand-700 hover:underline dark:text-brand-300" href="{{ route('register') }}">Créer un compte</a></p>
@endsection
