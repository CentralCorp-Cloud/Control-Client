@extends('layouts.admin')

@section('content')
<div class="mb-6 flex items-center justify-between"><h1 class="text-2xl font-bold">Versions CentralPanel</h1><x-button href="{{ route('admin.panel-versions.create') }}">Publier une version</x-button></div>
<x-card>
    <x-table><thead><tr><th class="px-4 py-3 text-left">Version</th><th class="px-4 py-3 text-left">Image épinglée</th><th class="px-4 py-3 text-left">État</th><th class="px-4 py-3"></th></tr></thead><tbody>
    @forelse($versions as $version)<tr class="border-t dark:border-slate-800"><td class="px-4 py-3 font-semibold">{{ $version->version }} @if($version->recommended)<x-badge>Recommandée</x-badge>@endif</td><td class="max-w-md truncate px-4 py-3 font-mono text-xs">{{ $version->image_reference }}</td><td class="px-4 py-3"><x-status-badge :status="$version->active?'active':'disabled'"/></td><td class="px-4 py-3 text-right"><x-button href="{{ route('admin.panel-versions.edit',$version) }}" variant="secondary">Modifier</x-button></td></tr>@empty<tr><td colspan="4" class="p-8"><x-empty-state title="Aucune version publiée"/></td></tr>@endforelse
    </tbody></x-table><div class="mt-4">{{ $versions->links() }}</div>
</x-card>
@endsection
