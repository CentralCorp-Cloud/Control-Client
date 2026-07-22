<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Models\Plan;
use App\Services\AuditService;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::orderBy('sort_order')->paginate(25);

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan]);
    }

    public function store(StorePlanRequest $request, AuditService $audit)
    {
        $plan = Plan::create(['uuid' => (string) Str::uuid(), ...$request->validated()]);
        $audit->record('plan.created', $plan);

        return redirect()->route('admin.plans.index')->with('success', 'Plan créé.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', compact('plan'));
    }

    public function update(StorePlanRequest $request, Plan $plan, AuditService $audit)
    {
        $data = $request->validated();
        if ($plan->projects()->exists()) {
            foreach (['is_free', 'price', 'billing_interval', 'memory_bytes', 'cpu_limit', 'stripe_price_id'] as $immutable) {
                unset($data[$immutable]);
            }
        }$plan->update($data);
        $audit->record('plan.updated', $plan);

        return back()->with('success', 'Plan mis à jour.');
    }

    public function destroy(Plan $plan, AuditService $audit)
    {
        $plan->update(['active' => false]);
        $audit->record('plan.deactivated', $plan);

        return back();
    }
}
