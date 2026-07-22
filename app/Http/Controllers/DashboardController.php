<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $query = auth()->user()->projects();
        $stats = ['total' => (clone $query)->count(), 'active' => (clone $query)->where('status', 'ACTIVE')->count(), 'provisioning' => (clone $query)->whereIn('status', ['PENDING_DOMAIN', 'PAYMENT_CONFIRMED', 'PENDING_CAPACITY', 'PROVISIONING'])->count(), 'failed' => (clone $query)->where('status', 'PROVISIONING_FAILED')->count()];
        $projects = $query->with(['plan', 'deployment'])->latest()->limit(6)->get();

        return view('dashboard', compact('stats', 'projects'));
    }
}
