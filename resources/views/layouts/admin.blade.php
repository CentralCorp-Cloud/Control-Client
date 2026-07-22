<!doctype html>
<html lang="fr">
<head>@include('layouts.head')</head>
<body data-app-shell>
<a href="#main-content" class="sr-only z-[100] rounded-lg bg-white px-4 py-3 text-sm font-semibold focus:not-sr-only focus:fixed focus:left-4 focus:top-4 dark:bg-slate-900">Aller au contenu</a>
<div class="min-h-dvh lg:grid lg:grid-cols-[272px_minmax(0,1fr)]">
    <div data-shell-overlay hidden class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" aria-hidden="true"></div>
    <aside data-shell-navigation tabindex="-1" class="fixed inset-y-0 left-0 z-50 flex w-[272px] -translate-x-full flex-col border-r border-slate-800 bg-slate-950 px-3 py-4 text-slate-200 transition-transform duration-200 lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0" aria-label="Navigation administration">
        <div class="flex h-11 items-center justify-between px-2">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 text-lg font-semibold tracking-tight text-white"><span class="flex size-8 items-center justify-center rounded-lg bg-white text-sm font-bold text-slate-950">C</span><span>CentralCloud</span></a>
            <button type="button" data-shell-close class="flex size-11 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 lg:hidden" aria-label="Fermer le menu"><x-icon name="x"/></button>
        </div>
        <div class="mx-2 mt-4 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2"><p class="text-xs font-semibold uppercase tracking-[0.12em] text-brand-300">Mode administration</p><p class="mt-1 text-xs text-slate-400">Control Plane</p></div>
        <nav class="mt-5 flex-1 overflow-y-auto" aria-label="Control Plane">
            @php($adminLinks=[
                ['dashboard','home','Vue d’ensemble'],['users.*','user','Utilisateurs'],['projects.*','server','CentralPanels'],['nodes.*','activity','Nodes'],['operations.*','activity','Opérations'],['plans.*','card','Plans'],['panel-versions.*','server','Versions'],['billing.*','card','Facturation'],['incidents.*','warning','Incidents'],['audit.*','shield','Journal d’audit'],['settings.*','settings','Paramètres']
            ])
            @foreach($adminLinks as [$pattern,$icon,$label])
                @php($route = 'admin.'.str_replace('.*','.index',$pattern))
                @if($pattern === 'dashboard') @php($route='admin.dashboard') @endif
                <a data-shell-close class="nav-link text-slate-300 hover:bg-slate-800 hover:text-white {{ request()->routeIs('admin.'.$pattern)?'bg-white text-slate-950 hover:bg-white hover:text-slate-950':'' }}" href="{{ route($route) }}"><x-icon :name="$icon"/>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="mt-4 border-t border-slate-800 pt-4"><a class="nav-link text-slate-300 hover:bg-slate-800 hover:text-white" href="{{ route('dashboard') }}"><x-icon name="arrow-right" class="rotate-180"/>Retour à l’espace client</a></div>
    </aside>
    <div class="min-w-0">
        <header class="sticky top-0 z-30 flex h-16 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-slate-800 dark:bg-slate-950/95">
            <button type="button" data-shell-open class="-ml-2 flex size-11 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Ouvrir le menu" aria-expanded="false"><x-icon name="menu"/></button>
            <div class="ml-auto flex items-center gap-3"><span class="hidden text-sm text-slate-500 sm:inline dark:text-slate-400">{{ auth()->user()->name }}</span><x-theme-switcher/></div>
        </header>
        <main id="main-content" class="page-shell" tabindex="-1">@include('partials.flash')@yield('content')</main>
    </div>
</div>
</body>
</html>
