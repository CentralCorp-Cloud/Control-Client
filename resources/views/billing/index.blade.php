@extends('layouts.dashboard')

@section('content')
<x-page-header title="Facturation" description="Gérez les abonnements liés à vos CentralPanel et accédez à vos moyens de paiement.">
    <x-slot:actions>
        @if($subscriptions->total() > 0)
            <form method="POST" action="{{ route('billing.portal') }}">
                @csrf
                <x-button variant="secondary">Ouvrir le portail Stripe <x-icon name="external"/></x-button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

@if($freeProjects->isNotEmpty())
    <x-card class="mb-6" title="Offres gratuites" description="Ces instances ne nécessitent aucun moyen de paiement.">
        <div class="divider-list">
            @foreach($freeProjects as $project)
                <div class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <a class="font-medium hover:text-brand-700 dark:hover:text-brand-300" href="{{ route('projects.show',$project->uuid) }}">{{ $project->name }}</a>
                        <p class="mt-1 text-sm text-slate-500">{{ $project->plan->name }} · aucune carte bancaire</p>
                    </div>
                    <x-status-badge :status="$project->status"/>
                </div>
            @endforeach
        </div>
    </x-card>
@endif

<x-table>
    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
        <tr>
            @foreach(['Projet','Plan','Statut','Fin de période'] as $heading)
                <th scope="col" class="px-5 py-3 text-left font-semibold">{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($subscriptions as $subscription)
            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                <td class="px-5 py-4 font-medium">{{ $subscription->project?->name }}</td>
                <td class="px-5 py-4">{{ $subscription->plan?->name }}</td>
                <td class="px-5 py-4"><x-status-badge :status="$subscription->stripe_status"/></td>
                <td class="px-5 py-4 tabular-nums">{{ $subscription->ends_at?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="p-10 text-center text-slate-500">Aucun abonnement Stripe.</td></tr>
        @endforelse
    </tbody>
</x-table>

<div class="mt-6">{{ $subscriptions->links() }}</div>
@endsection
