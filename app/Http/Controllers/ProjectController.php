<?php

namespace App\Http\Controllers;

use App\Enums\DomainMode;
use App\Enums\ProjectStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProvisioningRequest;
use App\Models\User;
use App\Services\DeploymentProvisioningService;
use App\Services\DomainNameService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = auth()->user()->projects()->with(['plan', 'deployment.node'])->latest()->paginate(12);

        return view('projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $plans = Plan::where('active', true)->orderBy('sort_order')->get();

        return view('projects.create', compact('plans'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request, DeploymentProvisioningService $provisioning)
    {
        $plan = Plan::where('active', true)->findOrFail($request->integer('plan_id'));
        $project = DB::transaction(function () use ($request, $plan) {
            $owner = User::query()->lockForUpdate()->findOrFail(auth()->id());
            if ($plan->is_free && $owner->projects()->whereHas('plan', fn ($query) => $query->where('is_free', true))->whereNotIn('status', [ProjectStatus::Cancelled, ProjectStatus::PendingDeletion])->exists()) {
                throw ValidationException::withMessages(['plan_id' => 'Vous possédez déjà un CentralPanel gratuit actif.']);
            }
            if ($plan->maximum_projects && $owner->projects()->where('plan_id', $plan->id)->whereNotIn('status', [ProjectStatus::Cancelled, ProjectStatus::PendingDeletion])->count() >= $plan->maximum_projects) {
                throw ValidationException::withMessages(['plan_id' => 'La limite de Projects pour ce Plan est atteinte.']);
            }
            $uuid = (string) Str::uuid();
            $mode = $plan->is_free ? DomainMode::CentralCloud : DomainMode::from($request->string('domain_mode')->toString());
            $canonicalHostname = $mode === DomainMode::CentralCloud
                ? DomainNameService::centralHostname($request->string('central_subdomain')->toString())
                : DomainNameService::opaqueCentralHostname($uuid);
            $project = Project::create([
                'uuid' => $uuid,
                'owner_id' => $owner->id,
                'plan_id' => $plan->id,
                'name' => $request->string('name'),
                'slug' => Str::slug($request->string('name')),
                'status' => $plan->is_free ? ProjectStatus::PaymentConfirmed : ProjectStatus::PendingPayment,
                'billing_type' => $plan->is_free ? 'FREE' : 'STRIPE',
                'domain_mode' => $mode,
                'canonical_hostname' => $canonicalHostname,
                'custom_hostname' => $mode === DomainMode::Custom ? $request->string('custom_url')->toString() : null,
            ]);
            ProvisioningRequest::create(['project_id' => $project->id, 'encrypted_bootstrap' => Crypt::encryptString(json_encode(['admin_name' => auth()->user()->name, 'admin_email' => $request->string('admin_email')->toString(), 'admin_password' => $request->string('admin_password')->toString()], JSON_THROW_ON_ERROR)), 'expires_at' => now()->addDay()]);

            return $project;
        });
        if ($plan->is_free) {
            try {
                $provisioning->provision($project);
            } catch (Throwable $exception) {
                report($exception);
            }

            return redirect()->route('projects.show', $project)->with('success', 'Votre CentralPanel gratuit a été enregistré et sera provisionné dès qu’une capacité sera disponible.');
        }
        if (! $plan->stripe_price_id) {
            return redirect()->route('projects.show', $project)->with('warning', 'Le paiement Stripe de ce plan doit être configuré par un administrateur.');
        }
        $checkout = auth()->user()->newSubscription('project:'.$project->uuid, $plan->stripe_price_id)->withMetadata(['project_uuid' => $project->uuid])->checkout(['success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}&project='.$project->uuid, 'cancel_url' => route('projects.show', $project), 'metadata' => ['project_uuid' => $project->uuid]]);
        $project->provisioningRequest->update(['stripe_checkout_session_id' => $checkout->asStripeCheckoutSession()->id]);

        return $checkout->redirect();
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        $this->authorize('view', $project);
        $project->load(['plan', 'deployment.operations' => fn ($q) => $q->latest()->limit(10)]);

        return view('projects.show', compact('project'));
    }
}
