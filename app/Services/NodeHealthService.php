<?php

namespace App\Services;

use App\Enums\NodeStatus;
use App\Exceptions\NodeAgentException;
use App\Models\Incident;
use App\Models\Node;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Support\Str;
use Throwable;

final class NodeHealthService
{
    public function __construct(private NodeAgentClient $client) {}

    public function poll(Node $node): void
    {
        try {
            $health = $this->client->health($node);
            if (($health['node_id'] ?? null) !== $node->agent_node_id) {
                $this->identityMismatch($node);

                return;
            } $resources = $this->client->resources($node);
            if (($resources['node_id'] ?? null) !== $node->agent_node_id) {
                $this->identityMismatch($node);

                return;
            } $capabilities = is_array($health['capabilities'] ?? null) ? array_values(array_filter($health['capabilities'], 'is_string')) : [];
            $node->update(['status' => $node->maintenance ? NodeStatus::Maintenance : NodeStatus::Online, 'last_health_status' => $health['status'] ?? 'ok', 'agent_version' => $health['agent_version'] ?? $health['version'] ?? null, 'capabilities' => $capabilities, 'cpu_count' => $resources['cpu_count'] ?? 0, 'memory_total_bytes' => $resources['memory_total_bytes'] ?? 0, 'memory_available_bytes' => $resources['memory_available_bytes'] ?? 0, 'disk_total_bytes' => $resources['disk_total_bytes'] ?? 0, 'disk_available_bytes' => $resources['disk_available_bytes'] ?? 0, 'deployment_count' => $resources['deployment_count'] ?? 0, 'active_deployment_count' => $resources['active_deployment_count'] ?? 0, 'last_seen_at' => now(), 'last_error_code' => null]);
            $this->resolve("node:{$node->id}:health");
        } catch (Throwable $e) {
            $code = $e instanceof NodeAgentException ? $e->agentCode : 'unreachable';
            $node->update(['status' => $code === 'degraded' ? NodeStatus::Degraded : NodeStatus::Offline, 'last_seen_at' => $code === 'degraded' ? now() : $node->last_seen_at, 'last_error_at' => now(), 'last_error_code' => $code]);
            $this->incident($node, "node:{$node->id}:health", 'CRITICAL', "Le Node {$node->name} ne répond pas correctement ({$code}).");
        }
    }

    private function identityMismatch(Node $node): void
    {
        $node->update(['status' => NodeStatus::Degraded, 'scheduling_enabled' => false, 'last_error_at' => now(), 'last_error_code' => 'node_identity_mismatch']);
        $this->incident($node, "node:{$node->id}:identity", 'CRITICAL', "L’identité Agent du Node {$node->name} a changé.");
    }

    private function incident(Node $node, string $fp, string $severity, string $message): void
    {
        Incident::updateOrCreate(['fingerprint' => $fp], ['uuid' => (string) Str::uuid(), 'severity' => $severity, 'source_type' => 'node', 'source_id' => (string) $node->id, 'message' => $message, 'status' => 'OPEN', 'first_seen_at' => Incident::where('fingerprint', $fp)->value('first_seen_at') ?? now(), 'last_seen_at' => now(), 'resolved_at' => null]);
    }

    private function resolve(string $fp): void
    {
        Incident::where('fingerprint', $fp)->where('status', '!=', 'RESOLVED')->update(['status' => 'RESOLVED', 'resolved_at' => now(), 'last_seen_at' => now()]);
    }
}
