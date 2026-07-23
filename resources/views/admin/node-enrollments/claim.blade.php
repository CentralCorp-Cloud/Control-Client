@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-xl">
    <x-page-header title="Associer un Node" subtitle="Saisissez le code affiché dans le terminal du serveur"/>
    <x-card>
        <form method="POST" action="{{ route('admin.node-enrollments.lookup') }}" class="space-y-4">
            @csrf
            <x-input label="Code d’association" name="code" value="{{ old('code', $code) }}" placeholder="XK7P-4N2Q" autocomplete="one-time-code" required autofocus/>
            <x-alert>Vérifiez le hostname, le système, l’architecture, la mémoire et les adresses détectées avant toute approbation.</x-alert>
            <x-button>Examiner le serveur</x-button>
        </form>
    </x-card>
</div>
@endsection
