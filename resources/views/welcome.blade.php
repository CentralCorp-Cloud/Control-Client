@extends('layouts.public')

@section('content')
<section class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
    <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-[1.1fr_0.9fr] lg:px-8">
        <div>
            <p class="eyebrow text-brand-700 dark:text-brand-300">Hébergement managé CentralPanel</p>
            <h1 class="mt-5 max-w-3xl text-4xl font-semibold leading-[1.08] tracking-[-0.035em] text-slate-950 sm:text-6xl dark:text-white">Votre panel opérationnel, sans gérer l’infrastructure.</h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-300">CentralCloud provisionne, supervise et maintient votre instance CentralPanel sur une infrastructure contrôlée.</p>
            <div class="mt-8 flex flex-wrap gap-3"><x-button href="{{ route('register') }}">Créer un CentralPanel <x-icon name="arrow-right"/></x-button><x-button variant="secondary" href="#fonctionnement">Découvrir le service</x-button></div>
        </div>
        <div class="surface p-3 sm:p-5" aria-label="Aperçu du service">
            <div class="rounded-lg bg-slate-950 p-5 text-white dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4"><div><p class="text-sm font-semibold">Production</p><p class="mt-1 text-xs text-slate-400">panel.example.cloud</p></div><span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-300"><span class="size-1.5 rounded-full bg-emerald-400"></span>Opérationnel</span></div>
                <dl class="mt-5 grid grid-cols-2 gap-3"><div class="rounded-lg border border-slate-800 p-4"><dt class="text-xs text-slate-400">Supervision</dt><dd class="mt-2 font-medium">Continue</dd></div><div class="rounded-lg border border-slate-800 p-4"><dt class="text-xs text-slate-400">Opérations</dt><dd class="mt-2 font-medium">Sécurisées</dd></div><div class="rounded-lg border border-slate-800 p-4"><dt class="text-xs text-slate-400">Déploiement</dt><dd class="mt-2 font-medium">Contrôlé</dd></div><div class="rounded-lg border border-slate-800 p-4"><dt class="text-xs text-slate-400">Isolation</dt><dd class="mt-2 font-medium">Par instance</dd></div></dl>
            </div>
        </div>
    </div>
</section>
<section id="fonctionnement" class="bg-slate-50 py-20 dark:bg-slate-950">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl"><p class="eyebrow">Un service spécialisé</p><h2 class="mt-3 text-3xl font-semibold tracking-tight">L’essentiel pour exploiter CentralPanel sereinement.</h2><p class="mt-4 leading-7 text-slate-600 dark:text-slate-400">Une interface claire pour suivre l’état de vos instances et déclencher les opérations nécessaires.</p></div>
        <div class="mt-10 grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-800 dark:bg-slate-800">
            @foreach([['server','Provisionnement','Une instance préparée selon les ressources de votre plan.'],['shield','Isolation','Chaque CentralPanel dispose de son environnement dédié.'],['activity','Supervision','État technique et opérations accessibles depuis votre espace.'],['card','Facturation','Abonnements et offres gratuites regroupés au même endroit.']] as [$icon,$title,$copy])
                <article class="bg-white p-6 dark:bg-slate-900"><span class="flex size-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"><x-icon :name="$icon"/></span><h3 class="mt-5 font-semibold">{{ $title }}</h3><p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $copy }}</p></article>
            @endforeach
        </div>
    </div>
</section>
@endsection
