<?php

namespace App\Console\Commands;

use App\Enums\AgentOperationStatus;
use App\Exceptions\NodeAgentException;
use App\Models\AgentRequest;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class RetryAgentRequests extends Command
{
    protected $signature = 'centralcloud:requests:retry {--limit=25}';

    protected $description = 'Retry uncertain Agent mutations with their original identity and body';

    public function handle(NodeAgentClient $client): int
    {
        AgentRequest::with(['operation.node'])->where('state', 'PENDING')->where('attempts', '<', 5)->whereNotNull('agent_operation_id')->orderBy('last_attempted_at')->limit((int) $this->option('limit'))->each(function (AgentRequest $request) use ($client) {
            $operation = $request->operation;
            if (! $operation) {
                return;
            }
            $payload = null;
            if ($request->encrypted_payload) {
                $decoded = json_decode(Crypt::decryptString($request->encrypted_payload), false, 512, JSON_THROW_ON_ERROR);
                if (! is_object($decoded)) {
                    throw new \UnexpectedValueException('Agent mutation payload must be a JSON object.');
                }
                $payload = get_object_vars($decoded);
            }
            $headers = $request->encrypted_headers ? json_decode(Crypt::decryptString($request->encrypted_headers), true, 512, JSON_THROW_ON_ERROR) : [];
            try {
                $response = $client->mutate($operation->node, $request->method, $request->path, $payload, $operation->idempotency_key, $operation->correlation_id, $headers);
                $operation->update(['agent_operation_id' => $response['operation_id'] ?? null, 'status' => strtoupper($response['status'] ?? 'QUEUED'), 'started_at' => now()]);
                $request->update(['state' => 'ACCEPTED', 'attempts' => $request->attempts + 1, 'last_attempted_at' => now(), 'accepted_at' => now(), 'encrypted_payload' => null, 'encrypted_headers' => null]);
            } catch (NodeAgentException $e) {
                $operation->update(['status' => AgentOperationStatus::Failed, 'completed_at' => now(), 'error_code' => $e->agentCode, 'error_message_sanitized' => $e->clientMessage()]);
                $request->update(['state' => 'REJECTED', 'attempts' => $request->attempts + 1, 'last_attempted_at' => now(), 'encrypted_payload' => null, 'encrypted_headers' => null]);
            } catch (Throwable) {
                $request->increment('attempts');
                $request->update(['last_attempted_at' => now()]);
            }
        });

        return self::SUCCESS;
    }
}
