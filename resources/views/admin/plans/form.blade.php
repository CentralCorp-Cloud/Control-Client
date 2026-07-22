@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-3xl"><h1 class="mb-6 text-2xl font-bold">{{ $plan->exists?'Modifier':'Créer' }} un Plan</h1>
<x-card><form method="POST" action="{{ $plan->exists?route('admin.plans.update',$plan):route('admin.plans.store') }}" class="grid gap-4 sm:grid-cols-2">@csrf @if($plan->exists)@method('PUT')@endif
    <x-input label="Nom" name="name" :value="$plan->name" required/><x-input label="Slug" name="slug" :value="$plan->slug" required/>
    <label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" name="is_free" value="1" @checked(old('is_free',$plan->is_free))> <span><strong>Plan gratuit</strong> — aucun Checkout ni abonnement Stripe, limité à un Project par utilisateur</span></label>
    <x-input label="Prix en centimes" name="price" type="number" min="0" :value="$plan->price ?? 0" required/><x-input label="Devise" name="currency" :value="$plan->currency ?? 'EUR'" required/>
    <x-select label="Intervalle" name="billing_interval"><option value="month" @selected($plan->billing_interval==='month')>Mensuel</option><option value="year" @selected($plan->billing_interval==='year')>Annuel</option></x-select><x-input label="Limite de Projects" name="maximum_projects" type="number" min="1" :value="$plan->maximum_projects"/>
    <x-input label="Stripe Product ID" name="stripe_product_id" :value="$plan->stripe_product_id"/><x-input label="Stripe Price ID" name="stripe_price_id" :value="$plan->stripe_price_id"/>
    <x-input label="Mémoire bytes" name="memory_bytes" type="number" :value="$plan->memory_bytes" required/><x-input label="CPU limit" name="cpu_limit" type="number" step="0.1" :value="$plan->cpu_limit" required/>
    <label class="sm:col-span-2"><span class="label">Description</span><textarea class="field" name="description">{{ old('description',$plan->description) }}</textarea></label>
    <label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked(old('active',$plan->exists?$plan->active:true))> Plan actif</label>
    <div class="sm:col-span-2"><x-button>Enregistrer</x-button></div>
</form></x-card></div>
@endsection
