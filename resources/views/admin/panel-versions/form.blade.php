@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-3xl"><h1 class="mb-6 text-2xl font-bold">{{ $panelVersion->exists?'Modifier':'Publier' }} une version CentralPanel</h1>
<x-card><form method="POST" action="{{ $panelVersion->exists?route('admin.panel-versions.update',$panelVersion):route('admin.panel-versions.store') }}" class="space-y-4">@csrf @if($panelVersion->exists)@method('PUT')@endif
    <x-input label="Version métier" name="version" :value="$panelVersion->version" placeholder="2.4.1" required/>
    <x-input label="Image officielle épinglée par digest" name="image_reference" :value="$panelVersion->image_reference" placeholder="ghcr.io/centralcorp/centralpanel@sha256:…" required/>
    <p class="text-sm text-slate-500">Seule l’image officielle CentralCorp épinglée avec un digest SHA-256 est acceptée.</p>
    <label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked(old('active',$panelVersion->exists?$panelVersion->active:true))> Active</label>
    <label class="flex items-center gap-2"><input type="checkbox" name="recommended" value="1" @checked(old('recommended',$panelVersion->recommended))> Version recommandée</label>
    <div class="flex gap-3"><x-button>Enregistrer</x-button><x-button href="{{ route('admin.panel-versions.index') }}" variant="secondary">Annuler</x-button></div>
</form></x-card>
@if($panelVersion->exists && $panelVersion->active)<form method="POST" action="{{ route('admin.panel-versions.destroy',$panelVersion) }}" class="mt-6">@csrf @method('DELETE')<x-button-danger>Désactiver cette version</x-button-danger></form>@endif
</div>
@endsection
