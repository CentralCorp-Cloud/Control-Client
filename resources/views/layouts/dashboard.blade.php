<!doctype html>
<html lang="fr">
<head>@include('layouts.head')</head>
<body data-app-shell>
<a href="#main-content" class="sr-only z-[100] rounded-lg bg-white px-4 py-3 text-sm font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4 dark:bg-slate-900">Aller au contenu</a>
<div class="min-h-dvh lg:grid lg:grid-cols-[256px_minmax(0,1fr)]">
    <div data-shell-overlay hidden class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" aria-hidden="true"></div>
    <aside data-shell-navigation tabindex="-1" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white px-3 py-4 transition-transform duration-200 lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0 dark:border-slate-800 dark:bg-slate-950" aria-label="Navigation principale">
        <div class="flex h-11 items-center justify-between px-2">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-lg font-semibold tracking-tight"><span class="flex size-8 items-center justify-center rounded-lg bg-slate-950 text-sm font-bold text-white dark:bg-white dark:text-slate-950">C</span><span>Central<span class="text-brand-600 dark:text-brand-300">Cloud</span></span></a>
            <button type="button" data-shell-close class="flex size-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800" aria-label="Fermer le menu"><x-icon name="x"/></button>
        </div>
        <nav class="mt-7 flex-1 overflow-y-auto" aria-label="Espace client">
            <p class="nav-section">Espace client</p>
            @foreach([
                ['dashboard','dashboard','home','Vue d’ensemble'],
                ['projects.*','projects.index','server','CentralPanel'],
                ['billing.*','billing.index','card','Facturation'],
                ['notifications.*','notifications.index','bell','Notifications'],
            ] as [$pattern,$route,$icon,$label])
                <a data-shell-close class="nav-link {{ request()->routeIs($pattern)?'nav-link-active':'' }}" href="{{ route($route) }}"><x-icon :name="$icon"/><span class="flex-1">{{ $label }}</span>@if($route==='notifications.index' && auth()->user()->unreadNotifications()->count())<span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">{{ auth()->user()->unreadNotifications()->count() }}</span>@endif</a>
            @endforeach
            <p class="nav-section">Préférences</p>
            <a data-shell-close class="nav-link {{ request()->routeIs('account.profile')?'nav-link-active':'' }}" href="{{ route('account.profile') }}"><x-icon name="user"/>Compte</a>
            <a data-shell-close class="nav-link {{ request()->routeIs('security.*')?'nav-link-active':'' }}" href="{{ route('security.index') }}"><x-icon name="shield"/>Sécurité</a>
            @can('access-admin')
                <p class="nav-section">Administration</p>
                <a class="nav-link" href="{{ route('admin.dashboard') }}"><x-icon name="settings"/>Ouvrir l’administration</a>
            @endcan
        </nav>
        <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
            <p class="truncate px-3 text-sm font-medium">{{ auth()->user()->name }}</p>
            <p class="truncate px-3 text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
            <form method="POST" action="{{ route('logout') }}" data-submit-once class="mt-2">@csrf<button class="nav-link w-full"><x-icon name="logout"/>Déconnexion</button></form>
        </div>
    </aside>
    <div class="min-w-0">
        <header class="sticky top-0 z-30 flex h-16 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-slate-800 dark:bg-slate-950/95">
            <button type="button" data-shell-open class="-ml-2 flex size-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Ouvrir le menu" aria-expanded="false"><x-icon name="menu"/></button>
            <div class="ml-auto flex items-center gap-3"><x-theme-switcher/></div>
        </header>
        <main id="main-content" class="page-shell" tabindex="-1">@include('partials.flash')@yield('content')</main>
    </div>
</div>
</body>
</html>
