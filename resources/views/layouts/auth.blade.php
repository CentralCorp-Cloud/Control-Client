<!doctype html>
<html lang="fr">
<head>@include('layouts.head')</head>
<body>
<main class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(440px,0.8fr)]">
    <section class="hidden border-r border-slate-200 bg-slate-950 p-12 text-white lg:flex lg:flex-col dark:border-slate-800" aria-label="CentralCloud">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold"><span class="flex size-8 items-center justify-center rounded-lg bg-white text-sm font-bold text-slate-950">C</span>CentralCloud</a>
        <div class="my-auto max-w-xl"><p class="text-sm font-semibold uppercase tracking-[0.14em] text-brand-300">Hébergement managé</p><p class="mt-5 text-4xl font-semibold leading-tight tracking-tight">Votre CentralPanel, sans la charge de l’infrastructure.</p><p class="mt-5 max-w-lg text-base leading-7 text-slate-300">Provisionnement contrôlé, supervision continue et opérations sécurisées depuis une interface unique.</p></div>
        <p class="text-sm text-slate-400">CentralCloud Control Plane</p>
    </section>
    <section class="flex min-h-dvh items-center justify-center px-4 py-12 sm:px-8">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center justify-between lg:justify-end"><a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold lg:hidden"><span class="flex size-8 items-center justify-center rounded-lg bg-slate-950 text-sm font-bold text-white dark:bg-white dark:text-slate-950">C</span>CentralCloud</a><x-theme-switcher/></div>
            @include('partials.flash')
            @yield('content')
        </div>
    </section>
</main>
</body>
</html>
