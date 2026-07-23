@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="Ajouter un Node" subtitle="Installation automatisée et association sécurisée"/>

    <x-card title="Installation interactive recommandée">
        <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">Exécutez ces commandes sur un serveur vierge, puis saisissez le code d’association affiché.</p>
        <pre class="overflow-x-auto rounded-lg bg-slate-950 p-4 text-sm text-slate-100"><code>curl -fsSL {{ config('centralcloud.enrollment.installer_url') }} -o /tmp/centralcloud-node.sh
sudo bash /tmp/centralcloud-node.sh</code></pre>
        <div class="mt-4"><a class="text-sm font-semibold text-brand-600" href="{{ route('admin.node-enrollments.claim') }}">Saisir un code d’association</a></div>
    </x-card>

    <x-card title="Installation automatique">
        <x-alert>Le token est affiché une seule fois. Placez-le dans un fichier root <code>0600</code>, jamais dans les arguments du processus.</x-alert>
        <form method="POST" action="{{ route('admin.node-enrollments.automatic') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
            @csrf
            <x-input label="Nom du Node" name="name" required/>
            <x-input label="Environnement" name="environment" value="production" required/>
            <x-input label="Région" name="region"/>
            <x-input label="FQDN Agent" name="agent_fqdn" placeholder="node-02.nodes.example.com" required/>
            <x-input label="Endpoint Agent" name="agent_endpoint" type="url" placeholder="https://node-02.nodes.example.com:9443" required/>
            <x-input label="Adresse publiée" name="published_address"/>
            <x-select label="Canal Agent" name="agent_channel"><option value="stable">stable</option><option value="beta">beta</option></x-select>
            <x-input label="Version Agent exacte" name="agent_version" value="{{ config('centralcloud.enrollment.default_agent_version') }}" required/>
            <div class="sm:col-span-2"><x-input label="CIDR Control Plane autorisé" name="allowed_source_cidrs[]" placeholder="203.0.113.0/24" required/></div>
            <x-input label="Capacité maximale" name="maximum_deployments" type="number" value="50"/>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="initial_maintenance" value="1"> Maintenance initiale</label>
            <div class="sm:col-span-2"><x-button>Créer le token à usage unique</x-button></div>
        </form>
    </x-card>

    <x-card title="Enregistrement manuel avancé">
        <form method="POST" action="{{ route('admin.nodes.store') }}" class="grid gap-4 sm:grid-cols-2">
            @csrf
            <x-input label="Nom" name="name" required/>
            <x-input label="Endpoint HTTPS Agent" name="endpoint" type="url" required/>
            <x-input label="Région" name="region"/>
            <div class="sm:col-span-2"><x-button variant="secondary">Tester et enregistrer</x-button></div>
        </form>
    </x-card>
</div>
@endsection
