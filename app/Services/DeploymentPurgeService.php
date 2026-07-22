<?php

namespace App\Services;

use App\Exceptions\NodeAgentException;
use App\Models\AgentOperation;
use App\Models\AgentRequest;
use App\Models\Deployment;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeploymentPurgeService
{
    public function __construct(private NodeAgentClient $client, private AgentMutationService $mutations) {}

    public function purge(Deployment $deployment): AgentOperation
    {
        $path = "/v1/deployments/{$deployment->uuid}/purge-token";
        $request = DB::transaction(function () use ($deployment, $path) {
            $locked = Deployment::query()->lockForUpdate()->findOrFail($deployment->id);
            if ($locked->hasActiveOperation()) {
                throw new \DomainException('Une opération est déjà en cours.');
            }
            $pending = AgentRequest::query()->where('deployment_id', $locked->id)->where('path', $path)->where('state', 'PENDING')->latest()->first();
            if ($pending) {
                return $pending;
            }
            $identity = $this->client->newMutationIdentity();

            return AgentRequest::create([
                'deployment_id' => $locked->id,
                'node_id' => $locked->node_id,
                'idempotency_key' => $identity['idempotency_key'],
                'correlation_id' => $identity['correlation_id'],
                'method' => 'POST',
                'path' => $path,
                'request_hash' => hash('sha256', "POST {$path}\nnull"),
                'state' => 'PENDING',
            ]);
        });

        $node = $deployment->node;
        if (! $node) {
            throw new \DomainException('Ce Deployment n’est associé à aucun Node.');
        }
        try {
            $response = $this->client->mutate($node, 'POST', $path, null, $request->idempotency_key, $request->correlation_id);
            $request->update(['state' => 'ACCEPTED', 'attempts' => $request->attempts + 1, 'last_attempted_at' => now(), 'accepted_at' => now()]);
        } catch (NodeAgentException $e) {
            $request->update(['state' => 'REJECTED', 'attempts' => $request->attempts + 1, 'last_attempted_at' => now()]);
            throw $e;
        } catch (Throwable $e) {
            $request->increment('attempts');
            $request->update(['last_attempted_at' => now()]);
            throw $e;
        }

        $token = $response['purge_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Invalid purge token response.');
        }

        return $this->mutations->dispatch($deployment, 'delete_purge', 'DELETE', "/v1/deployments/{$deployment->uuid}?mode=purge", null, ['X-Purge-Token' => $token]);
    }
}
