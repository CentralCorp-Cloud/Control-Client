<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingTransaction;
use App\Models\StripeEvent;
use App\Models\Subscription;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = Subscription::with(['user', 'project', 'plan'])->when($request->filled('status'), fn ($q) => $q->where('stripe_status', $request->status))->latest()->paginate(25);
        $transactions = BillingTransaction::latest()->limit(20)->get();
        $events = StripeEvent::latest()->limit(20)->get();

        return view('admin.billing.index', compact('subscriptions', 'transactions', 'events'));
    }
}
