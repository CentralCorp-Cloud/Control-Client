@extends('layouts.admin')

@section('content')
<x-page-header title="Installations de Nodes" subtitle="Enrôlements, progression et validation mTLS">
    <a href="{{ route('admin.nodes.create') }}"><x-button>Ajouter un Node</x-button></a>
</x-page-header>
<x-card>
    <x-table>
        <x-slot:head><tr><th>Node</th><th>Serveur détecté</th><th>Mode</th><th>Étape</th><th>Statut</th><th>Activité</th></tr></x-slot:head>
        @forelse($enrollments as $enrollment)
            <tr>
                <td><a class="font-semibold text-brand-600" href="{{ route('admin.node-enrollments.show', $enrollment) }}">{{ $enrollment->chosen_name ?: 'En attente' }}</a></td>
                <td>{{ $enrollment->hostname ?: '—' }}</td>
                <td>{{ $enrollment->mode->value }}</td>
                <td>{{ $enrollment->step->value }} · {{ $enrollment->percentage }}%</td>
                <td><x-status-badge :status="$enrollment->status->value"/></td>
                <td>{{ $enrollment->last_activity_at?->diffForHumans() ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-8 text-center text-slate-500">Aucun enrôlement.</td></tr>
        @endforelse
    </x-table>
    <div class="mt-4">{{ $enrollments->links() }}</div>
</x-card>
@endsection
