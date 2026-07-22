<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index()
    {
        $subscriptions = auth()->user()->subscriptions()->with(['project', 'plan'])->latest()->paginate(15);
        $freeProjects = auth()->user()->projects()->with('plan')->where('billing_type', 'FREE')->latest()->get();

        return view('billing.index', compact('subscriptions', 'freeProjects'));
    }

    public function success(Request $request)
    {
        $project = $request->user()->projects()->where('uuid', $request->string('project')->toString())->first();

        return view('billing.success', compact('project'));
    }

    public function portal()
    {
        if (! auth()->user()->subscriptions()->exists()) {
            return back()->with('warning', 'Aucun abonnement Stripe n’est associé à votre compte.');
        }

        return auth()->user()->redirectToBillingPortal(route('billing.index'));
    }
}
