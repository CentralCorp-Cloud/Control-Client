<?php

namespace App\Services;

use App\Contracts\CnameResolver;
use App\Enums\ProjectStatus;
use App\Models\Project;

final class DomainVerificationService
{
    public function __construct(
        private CnameResolver $resolver,
        private DeploymentProvisioningService $provisioning,
    ) {}

    public function verify(Project $project): bool
    {
        $project->refresh();
        if (! $project->isCustomDomain() || ! $project->custom_hostname) {
            return true;
        }
        if ($project->domain_verified_at) {
            return true;
        }

        $target = $this->resolver->resolve($project->custom_hostname);
        $verified = is_string($project->canonical_hostname)
            && $project->canonical_hostname !== ''
            && is_string($target)
            && $target !== ''
            && hash_equals(strtolower($project->canonical_hostname), strtolower($target));
        $project->update([
            'domain_last_checked_at' => now(),
            'domain_check_error' => $verified ? null : ($target ? 'Le CNAME pointe actuellement vers '.$target.'.' : 'Aucun enregistrement CNAME direct n’a été trouvé.'),
            'domain_verified_at' => $verified ? now() : null,
            'status' => $verified && $project->status === ProjectStatus::PendingDomain ? ProjectStatus::PaymentConfirmed : $project->status,
        ]);

        if ($verified && $project->payment_confirmed_at && ! $project->deployment) {
            $this->provisioning->provision($project->fresh());
        }

        return $verified;
    }
}
