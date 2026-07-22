<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DomainVerificationService;
use Throwable;

class DomainVerificationController extends Controller
{
    public function __invoke(Project $project, DomainVerificationService $verification)
    {
        $this->authorize('view', $project);
        abort_unless($project->isCustomDomain(), 404);
        abort_unless($project->payment_confirmed_at !== null, 409, 'Le paiement doit être confirmé avant la vérification DNS.');

        try {
            $verified = $verification->verify($project);
        } catch (Throwable $exception) {
            report($exception);
            $verified = $project->fresh()->domain_verified_at !== null;
        }

        return back()->with($verified ? 'success' : 'warning', $verified
            ? 'Le domaine personnalisé est vérifié. Le provisionnement peut commencer.'
            : 'Le CNAME attendu n’est pas encore visible. Vérifiez votre configuration DNS puis réessayez.');
    }
}
