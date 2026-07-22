<?php

namespace App\Services;

use App\Models\Deployment;

final class DeploymentLifecycleService
{
    public function __construct(private AgentMutationService $mutations) {}

    public function start(Deployment $d)
    {
        return $this->mutations->dispatch($d, 'start', 'POST', "/v1/deployments/{$d->uuid}/start");
    }

    public function stop(Deployment $d)
    {
        return $this->mutations->dispatch($d, 'stop', 'POST', "/v1/deployments/{$d->uuid}/stop");
    }

    public function restart(Deployment $d)
    {
        return $this->mutations->dispatch($d, 'restart', 'POST', "/v1/deployments/{$d->uuid}/restart");
    }

    public function adminReset(Deployment $d, string $email, string $password)
    {
        return $this->mutations->dispatch($d, 'admin_reset', 'POST', "/v1/deployments/{$d->uuid}/admin-reset", ['admin_email' => $email, 'admin_password' => $password]);
    }

    public function softDelete(Deployment $d)
    {
        return $this->mutations->dispatch($d, 'delete_soft', 'DELETE', "/v1/deployments/{$d->uuid}?mode=soft");
    }

    public function upgrade(Deployment $d, string $image)
    {
        return $this->mutations->dispatch($d, 'upgrade', 'POST', "/v1/deployments/{$d->uuid}/upgrade", ['image' => $image]);
    }
}
