@extends('layouts.admin')

@section('content')
<div class="space-y-6"
     x-data="{status: @js($enrollment->status->value), terminal: @js($enrollment->status->terminal())}"
     x-init="if (!terminal) setInterval(async () => { const r = await fetch(@js(route('admin.node-enrollments.status', $enrollment)), {headers: {'Accept': 'application/json'}}); if (r.ok) { const v = await r.json(); if (v.status !== status || v.percentage !== @js($enrollment->percentage)) location.reload(); terminal = v.terminal; } }, 5000)">
    <x-page-header :title="$enrollment->chosen_name ?: 'Enrôlement '.$enrollment->uuid" :subtitle="$enrollment->hostname ?: 'Serveur non connecté'"/>

    @if(session('one_time_enrollment_token'))
        <x-alert type="warning">
            <strong>Token à copier maintenant — il ne sera plus affiché :</strong>
            <pre class="mt-3 overflow-x-auto rounded bg-slate-950 p-3 text-white"><code>{{ session('one_time_enrollment_token') }}</code></pre>
            <p class="mt-3">Écrivez-le dans <code>/run/secrets/centralcloud-enrollment-token</code> avec les permissions <code>0600</code>, puis utilisez <code>--token-file</code>.</p>
            <pre class="mt-3 overflow-x-auto rounded bg-slate-950 p-3 text-white"><code>#cloud-config
write_files:
  - path: /run/secrets/centralcloud-enrollment-token
    owner: root:root
    permissions: "0600"
    content: |
      {{ session('one_time_enrollment_token') }}
runcmd:
  - [curl, -fsSL, {{ config('centralcloud.enrollment.installer_url') }}, -o, /tmp/centralcloud-node.sh]
  - [bash, /tmp/centralcloud-node.sh, --non-interactive, --token-file, /run/secrets/centralcloud-enrollment-token, --delete-token-file]</code></pre>
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-stat-card label="Statut" :value="$enrollment->status->value"/>
        <x-stat-card label="Étape" :value="$enrollment->step->value"/>
        <x-stat-card label="Progression" :value="$enrollment->percentage.' %'"/>
    </div>

    <x-card title="Serveur détecté">
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">Système</dt><dd>{{ $enrollment->os }} {{ $enrollment->os_version }}</dd></div>
            <div><dt class="text-slate-500">Architecture</dt><dd>{{ $enrollment->architecture ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Hostname</dt><dd>{{ $enrollment->hostname ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Mémoire</dt><dd>{{ $enrollment->memory_bytes ? round($enrollment->memory_bytes / 1073741824, 1).' Gio' : '—' }}</dd></div>
            <div><dt class="text-slate-500">Disque</dt><dd>{{ $enrollment->disk_bytes ? round($enrollment->disk_bytes / 1073741824, 1).' Gio' : '—' }}</dd></div>
            <div><dt class="text-slate-500">Installateur</dt><dd>{{ $enrollment->installer_version ?: '—' }}</dd></div>
        </dl>
    </x-card>

    @if(in_array($enrollment->status, [\App\Enums\NodeEnrollmentStatus::PendingClaim, \App\Enums\NodeEnrollmentStatus::AwaitingApproval], true))
        <x-card title="Approuver l’installation">
            <form method="POST" action="{{ route('admin.node-enrollments.approve', $enrollment) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <x-input label="Nom" name="name" value="{{ old('name', $enrollment->hostname) }}" required/>
                <x-input label="Environnement" name="environment" value="production" required/>
                <x-input label="Région" name="region"/>
                <x-input label="FQDN Agent" name="agent_fqdn" required/>
                <x-input label="Endpoint Agent" name="agent_endpoint" type="url" placeholder="https://node.example.com:9443" required/>
                <x-input label="Adresse publiée" name="published_address"/>
                <x-select label="Canal" name="agent_channel"><option>stable</option><option>beta</option></x-select>
                <x-input label="Version Agent" name="agent_version" value="{{ config('centralcloud.enrollment.default_agent_version') }}" required/>
                <div class="sm:col-span-2"><x-input label="CIDR Control Plane" name="allowed_source_cidrs[]" required/></div>
                <x-input label="Capacité maximale" name="maximum_deployments" type="number" value="50"/>
                <label class="flex items-center gap-2"><input type="checkbox" name="initial_maintenance" value="1"> Maintenance initiale</label>
                <div class="flex gap-3 sm:col-span-2"><x-button>Approuver</x-button></div>
            </form>
            <form method="POST" action="{{ route('admin.node-enrollments.deny', $enrollment) }}" class="mt-4">@csrf <x-button-danger>Refuser</x-button-danger></form>
        </x-card>
    @endif

    <x-card title="Progression">
        <p>{{ $enrollment->public_message ?: 'Aucun message.' }}</p>
        @if($enrollment->error_code)<x-alert type="danger" class="mt-4"><strong>{{ $enrollment->error_code }}</strong> — {{ $enrollment->sanitized_error }}</x-alert>@endif
        @if($enrollment->node)<a class="mt-4 inline-block text-brand-600" href="{{ route('admin.nodes.show', $enrollment->node) }}">Voir le Node</a>@endif
    </x-card>

    @if(!$enrollment->status->terminal())
        <form method="POST" action="{{ route('admin.node-enrollments.revoke', $enrollment) }}">@csrf <x-button-danger>Révoquer l’enrôlement</x-button-danger></form>
    @elseif(in_array($enrollment->status, [\App\Enums\NodeEnrollmentStatus::Failed, \App\Enums\NodeEnrollmentStatus::Validating], true))
        <form method="POST" action="{{ route('admin.node-enrollments.retry', $enrollment) }}">@csrf <x-button>Relancer la validation</x-button></form>
    @endif
</div>
@endsection
