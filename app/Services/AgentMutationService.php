<?php

namespace App\Services;

use App\Enums\AgentOperationStatus;
use App\Exceptions\NodeAgentException;
use App\Models\AgentOperation;
use App\Models\AgentRequest;
use App\Models\Deployment;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class AgentMutationService
{
    public function __construct(private NodeAgentClient $client) {}

    public function dispatch(Deployment $deployment, string $type, string $method, string $path, ?array $payload = null, array $secretHeaders = []): AgentOperation
    {
        [$operation,$request] = DB::transaction(function () use ($deployment, $type, $method, $path, $payload, $secretHeaders) {
            $locked = Deployment::query()->lockForUpdate()->findOrFail($deployment->id);
            if ($locked->hasActiveOperation()) {
                throw new \DomainException('Une opération est déjà en cours.');
            } $ids = $this->client->newMutationIdentity();
            $op = AgentOperation::create(['uuid' => (string) Str::uuid(), 'deployment_id' => $locked->id, 'node_id' => $locked->node_id, 'type' => $type, 'status' => AgentOperationStatus::Queued, 'idempotency_key' => $ids['idempotency_key'], 'correlation_id' => $ids['correlation_id']]);
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $req = AgentRequest::create(['agent_operation_id' => $op->id, 'deployment_id' => $locked->id, 'node_id' => $locked->node_id, 'idempotency_key' => $ids['idempotency_key'], 'correlation_id' => $ids['correlation_id'], 'method' => strtoupper($method), 'path' => $path, 'request_hash' => hash('sha256', strtoupper($method).' '.$path."\n".$json), 'encrypted_payload' => $payload === null ? null : Crypt::encryptString($json), 'encrypted_headers' => $secretHeaders ? Crypt::encryptString(json_encode($secretHeaders, JSON_THROW_ON_ERROR)) : null]);

            return [$op, $req];
        });
        $node = $deployment->node;
        if (! $node) {
            throw new \DomainException('Ce Deployment n’est associé à aucun Node.');
        }
        try {
            $response = $this->client->mutate($node, $request->method, $request->path, $payload, $operation->idempotency_key, $operation->correlation_id, $secretHeaders);
            $operation->update(['agent_operation_id' => $response['operation_id'] ?? null, 'status' => strtoupper($response['status'] ?? 'queued'), 'started_at' => now()]);
            $request->update(['state' => 'ACCEPTED', 'attempts' => 1, 'last_attempted_at' => now(), 'accepted_at' => now(), 'encrypted_payload' => null, 'encrypted_headers' => null]);

            return $operation->refresh();
        } catch (NodeAgentException $e) {
            $operation->update(['status' => AgentOperationStatus::Failed, 'completed_at' => now(), 'error_code' => $e->agentCode, 'error_message_sanitized' => $e->clientMessage()]);
            $request->update(['state' => 'REJECTED', 'attempts' => $request->attempts + 1, 'last_attempted_at' => now(), 'encrypted_payload' => null, 'encrypted_headers' => null]);
            throw $e;
        } catch (Throwable $e) {
            $request->increment('attempts');
            $request->update(['last_attempted_at' => now()]);
            throw $e;
        }
    }
}
