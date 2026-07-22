<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\AgentOperation;
use App\Models\Incident;
use App\Notifications\DeploymentFailedNotification;
use App\Notifications\PanelReadyNotification;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class PollAgentOperations extends Command
{
    protected $signature = 'centralcloud:operations:poll {--limit=100}';

    protected $description = 'Poll non-terminal Agent operations';

    public function handle(NodeAgentClient $client): int
    {
        AgentOperation::with(['node', 'deployment.project'])->whereIn('status', ['QUEUED', 'RUNNING'])->whereNotNull('agent_operation_id')->limit((int) $this->option('limit'))->each(function (AgentOperation $op) use ($client) {
            try {
                $data = $client->operation($op->node, $op->agent_operation_id);
                $status = strtoupper($data['status'] ?? 'RUNNING');
                $op->update(['status' => $status, 'last_polled_at' => now(), 'completed_at' => in_array($status, ['SUCCEEDED', 'FAILED'], true) ? now() : null, 'error_code' => $data['error']['code'] ?? null, 'error_message_sanitized' => isset($data['error']['message']) ? mb_substr($data['error']['message'], 0, 1000) : null]);
                if ($status === 'SUCCEEDED') {
                    $this->succeeded($op);
                } elseif ($status === 'FAILED') {
                    $this->failed($op);
                }
            } catch (Throwable) {
                $op->update(['last_polled_at' => now()]);
            }
        });

        return self::SUCCESS;
    }

    private function succeeded(AgentOperation $op): void
    {
        $d = $op->deployment;
        if ($op->type === 'create') {
            $d->project->update(['status' => ProjectStatus::Active]);
            $d->update(['state' => 'active', 'deployed_at' => now(), 'failure_code' => null, 'failure_message_sanitized' => null]);
            $d->project->owner->notify(new PanelReadyNotification($d->project));
        }
        if ($op->type === 'delete_purge') {
            $d->update(['state' => 'deleted']);
            $d->project->update(['status' => ProjectStatus::Cancelled, 'cancelled_at' => now()]);
        }
        Incident::where('fingerprint', "operation:{$op->id}")->update(['status' => 'RESOLVED', 'resolved_at' => now()]);
    }

    private function failed(AgentOperation $op): void
    {
        $d = $op->deployment;
        $d->update(['state' => 'failed', 'failed_at' => now(), 'failure_code' => $op->error_code, 'failure_message_sanitized' => $op->error_message_sanitized]);
        if ($op->type === 'create') {
            $d->project->update(['status' => ProjectStatus::ProvisioningFailed]);
            $d->project->owner->notify(new DeploymentFailedNotification($d->project));
        }Incident::updateOrCreate(['fingerprint' => "operation:{$op->id}"], ['uuid' => (string) Str::uuid(), 'severity' => 'HIGH', 'source_type' => 'operation', 'source_id' => (string) $op->id, 'message' => "L’opération {$op->type} a échoué.", 'status' => 'OPEN', 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }
}
