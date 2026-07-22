<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Exceptions\NodeAgentException;
use App\Models\AgentOperation;
use App\Models\Deployment;
use App\Models\Incident;
use App\Models\PanelVersion;
use App\Models\Project;
use App\Notifications\DeploymentFailedNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DeploymentProvisioningService
{
    public function __construct(private NodeSelectionService $selector, private AgentMutationService $mutations) {}

    public function provision(Project $project): ?AgentOperation
    {
        $project->loadMissing('plan', 'owner', 'provisioningRequest');
        if ($project->isCustomDomain() && ! $project->domain_verified_at) {
            $project->update(['status' => ProjectStatus::PendingDomain]);

            return null;
        }
        $node = $this->selector->select($project);
        if (! $node) {
            $project->update(['status' => ProjectStatus::PendingCapacity]);

            return null;
        }
        $bootstrap = json_decode(Crypt::decryptString($project->provisioningRequest->encrypted_bootstrap), true, 512, JSON_THROW_ON_ERROR);
        $deployment = DB::transaction(function () use ($project, $node) {
            $id = (string) Str::uuid();
            $image = PanelVersion::where('recommended', true)->where('active', true)->value('image_reference') ?: config('centralcloud.panel.image');
            if (! $image) {
                throw new \RuntimeException('CENTRALPANEL_IMAGE is not configured.');
            } if (app()->environment('production') && ! preg_match('/@sha256:[a-f0-9]{64}$/', $image)) {
                throw new \RuntimeException('Production panel image must be pinned by digest.');
            }

            return Deployment::firstOrCreate(['project_id' => $project->id], ['uuid' => $id, 'node_id' => $node->id, 'hostname' => $project->canonical_hostname ?: Str::lower(Str::random(20)).'.'.config('centralcloud.panel.domain_suffix'), 'state' => 'pending', 'desired_state' => 'active', 'memory_bytes' => $project->plan->memory_bytes, 'cpu_limit' => $project->plan->cpu_limit, 'image_reference' => $image, 'provisioning_started_at' => now()]);
        });
        $suffix = str_replace('-', '', substr($deployment->uuid, 0, 12));
        $payload = ['deployment_id' => $deployment->uuid, 'project_id' => $project->uuid, 'hostname' => $deployment->hostname, 'image' => $deployment->image_reference, 'environment' => (object) [], 'resources' => ['memory_bytes' => $deployment->memory_bytes, 'cpu_limit' => $deployment->cpu_limit], 'database' => ['database_name' => 'panel_'.$suffix.'_db', 'username' => 'panel_'.$suffix.'_user'], 'healthcheck' => ['path' => config('centralcloud.panel.health_path'), 'timeout_seconds' => config('centralcloud.panel.health_timeout')], 'bootstrap' => ['admin_name' => $bootstrap['admin_name'], 'admin_email' => $bootstrap['admin_email'], 'admin_password' => $bootstrap['admin_password'], 'internal_secret' => Str::random(64)]];
        if ($project->isCustomDomain()) {
            $payload['aliases'] = [$project->custom_hostname];
        }
        $project->update(['status' => ProjectStatus::Provisioning]);
        try {
            $operation = $this->mutations->dispatch($deployment, 'create', 'POST', '/v1/deployments', $payload);
        } catch (NodeAgentException $exception) {
            $capacity = $exception->agentCode === 'capacity_exceeded';
            $project->update(['status' => $capacity ? ProjectStatus::PendingCapacity : ProjectStatus::ProvisioningFailed]);
            $deployment->update(['state' => $capacity ? 'pending' : 'failed', 'failed_at' => $capacity ? null : now(), 'failure_code' => $exception->agentCode, 'failure_message_sanitized' => $exception->clientMessage()]);
            Incident::updateOrCreate(
                ['fingerprint' => "deployment:{$deployment->id}:provisioning"],
                ['uuid' => (string) Str::uuid(), 'severity' => $capacity ? 'MEDIUM' : 'HIGH', 'source_type' => 'deployment', 'source_id' => (string) $deployment->id, 'message' => $capacity ? 'Capacité insuffisante pour le provisioning.' : 'Le provisioning a été rejeté par le Node Agent.', 'status' => 'OPEN', 'first_seen_at' => now(), 'last_seen_at' => now()]
            );
            if (! $capacity) {
                $project->owner->notify(new DeploymentFailedNotification($project));
            }
            throw $exception;
        }
        $project->provisioningRequest->update(['encrypted_bootstrap' => Crypt::encryptString('{}'), 'consumed_at' => now()]);

        return $operation;
    }
}
