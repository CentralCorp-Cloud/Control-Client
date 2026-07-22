@extends('layouts.admin')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">{{ $user->name }}</h1><p class="text-slate-500">{{ $user->email }} · {{ $user->uuid }}</p></div>
<div class="grid gap-6 lg:grid-cols-2">
    <x-card title="Compte">
        <dl class="space-y-3 text-sm"><div><dt class="text-slate-500">Rôle</dt><dd><x-status-badge :status="$user->role"/></dd></div><div><dt class="text-slate-500">Statut</dt><dd><x-status-badge :status="$user->status"/></dd></div><div><dt class="text-slate-500">Email vérifié</dt><dd>{{ $user->email_verified_at?'Oui':'Non' }}</dd></div><div><dt class="text-slate-500">2FA</dt><dd>{{ $user->hasEnabledTwoFactorAuthentication()?'Active':'Inactive' }}</dd></div></dl>
        <div class="mt-6 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.users.update',$user->uuid) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $user->status->value==='ACTIVE'?'SUSPENDED':'ACTIVE' }}"><x-button-danger>{{ $user->status->value==='ACTIVE'?'Suspendre':'Réactiver' }}</x-button-danger></form>
            <form method="POST" action="{{ route('admin.users.sessions.destroy',$user->uuid) }}">@csrf @method('DELETE')<x-button variant="secondary">Révoquer les sessions</x-button></form>
            @if(!$user->email_verified_at)<form method="POST" action="{{ route('admin.users.verification',$user->uuid) }}">@csrf<x-button variant="secondary">Renvoyer la vérification</x-button></form>@endif
        </div>
        @can('purge-deployments')<form method="POST" action="{{ route('admin.users.update',$user->uuid) }}" class="mt-6 flex items-end gap-2">@csrf @method('PATCH')<div class="flex-1"><x-select label="Rôle" name="role">@foreach(App\Enums\UserRole::cases() as $role)<option value="{{ $role->value }}" @selected($user->role===$role)>{{ $role->value }}</option>@endforeach</x-select></div><x-button>Modifier le rôle</x-button></form>@endcan
    </x-card>
    <x-card title="Projects">@forelse($user->projects as $project)<a class="mb-2 flex justify-between rounded-lg bg-slate-50 p-3 dark:bg-slate-800" href="{{ route('admin.projects.show',$project->uuid) }}"><span>{{ $project->name }}</span><x-status-badge :status="$project->status"/></a>@empty<p class="text-slate-500">Aucun Project.</p>@endforelse</x-card>
</div>
@endsection
