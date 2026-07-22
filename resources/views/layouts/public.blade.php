<!doctype html>
<html lang="fr">
<head>@include('layouts.head')</head>
<body>
<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50">Aller au contenu</a>
<header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8" aria-label="Navigation principale">
        <a class="flex items-center gap-2 text-lg font-semibold tracking-tight" href="{{ route('home') }}"><span class="flex size-8 items-center justify-center rounded-lg bg-slate-950 text-sm font-bold text-white dark:bg-white dark:text-slate-950">C</span>Central<span class="-ml-2 text-brand-600 dark:text-brand-300">Cloud</span></a>
        <div class="flex items-center gap-2"><x-theme-switcher/>@auth<x-button href="{{ route('dashboard') }}">Espace client</x-button>@else<x-button variant="ghost" href="{{ route('login') }}">Connexion</x-button><x-button class="hidden sm:inline-flex" href="{{ route('register') }}">Créer un compte</x-button>@endauth</div>
    </nav>
</header>
<main id="main-content">{{ $slot ?? '' }}@yield('content')</main>
<footer class="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"><div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8 dark:text-slate-400"><p>CentralCloud — Hébergement managé CentralPanel</p><p>Infrastructure supervisée, opérations sécurisées.</p></div></footer>
</body>
</html>
