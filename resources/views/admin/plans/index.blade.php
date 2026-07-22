@extends('layouts.admin')

@section('content')
<div class="mb-6 flex justify-between"><h1 class="text-2xl font-bold">Plans</h1><x-button href="{{ route('admin.plans.create') }}">Créer un Plan</x-button></div>
<x-table><thead><tr>@foreach(['Plan','Prix','Ressources','Stripe Price','Limite','Actif','Actions'] as $h)<th class="px-4 py-3 text-left">{{ $h }}</th>@endforeach</tr></thead><tbody>
@foreach($plans as $plan)<tr class="border-t dark:border-slate-800"><td class="px-4 py-3 font-medium">{{ $plan->name }} @if($plan->is_free)<x-badge>Gratuit</x-badge>@endif</td><td class="px-4 py-3">{{ $plan->is_free?'Gratuit':number_format($plan->price/100,2,',',' ').' '.$plan->currency }}</td><td class="px-4 py-3">{{ round($plan->memory_bytes/1048576) }} Mio / {{ $plan->cpu_limit }} CPU</td><td class="px-4 py-3 font-mono text-xs">{{ $plan->is_free?'Non requis':($plan->stripe_price_id ?? '—') }}</td><td class="px-4 py-3">{{ $plan->maximum_projects ?? '—' }}</td><td class="px-4 py-3">{{ $plan->active?'Oui':'Non' }}</td><td class="px-4 py-3"><a class="text-brand-600" href="{{ route('admin.plans.edit',$plan) }}">Modifier</a></td></tr>@endforeach
</tbody></x-table><div class="mt-4">{{ $plans->links() }}</div>
@endsection
