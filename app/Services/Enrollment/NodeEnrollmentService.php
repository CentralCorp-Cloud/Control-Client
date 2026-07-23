<?php

namespace App\Services\Enrollment;

use App\Contracts\NodeCertificateIssuer;
use App\Enums\NodeEnrollmentMode;
use App\Enums\NodeEnrollmentStatus;
use App\Enums\NodeEnrollmentStep;
use App\Enums\NodeStatus;
use App\Models\Node;
use App\Models\NodeEnrollment;
use App\Services\AuditService;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NodeEnrollmentService
{
    public function __construct(
        private EnrollmentSecrets $secrets,
        private EnrollmentIdempotency $idempotency,
        private NodeCertificateIssuer $issuer,
        private NodeAgentClient $agent,
        private AuditService $audit,
    ) {}

    public function createDevice(array $metadata): array
    {
        if (! config('centralcloud.enrollment.enabled')) {
            throw new EnrollmentException('enrollment_disabled', 403, 'Node enrollment is disabled.');
        }
        $deviceCode = $this->secrets->token();
        do {
            $userCode = $this->secrets->userCode();
            $userHash = $this->secrets->hashUserCode($userCode);
        } while (NodeEnrollment::where('user_code_hash', $userHash)->where('expires_at', '>', now())->exists());

        $enrollment = NodeEnrollment::create([
            'uuid' => (string) Str::uuid(),
            'device_code_hash' => $this->secrets->hash($deviceCode),
            'user_code_hash' => $userHash,
            'status' => NodeEnrollmentStatus::PendingClaim,
            'mode' => NodeEnrollmentMode::Interactive,
            ...$this->metadata($metadata),
            'agent_channel' => $metadata['requested_channel'] ?? config('centralcloud.enrollment.default_agent_channel'),
            'requested_agent_version' => config('centralcloud.enrollment.default_agent_version'),
            'step' => NodeEnrollmentStep::WaitingForClaim,
            'correlation_id' => (string) Str::uuid(),
            'poll_interval' => max(5, (int) config('centralcloud.enrollment.poll_interval')),
            'expires_at' => now()->addSeconds((int) config('centralcloud.enrollment.ttl')),
            'last_activity_at' => now(),
        ]);
        $uri = route('admin.node-enrollments.claim');

        return [
            'enrollment_id' => $enrollment->uuid,
            'device_code' => $deviceCode,
            'user_code' => $userCode,
            'verification_uri' => $uri,
            'verification_uri_complete' => $uri.'?code='.urlencode($userCode),
            'expires_in' => (int) config('centralcloud.enrollment.ttl'),
            'interval' => $enrollment->poll_interval,
            'correlation_id' => $enrollment->correlation_id,
        ];
    }

    public function poll(string $deviceCode): array
    {
        $enrollment = NodeEnrollment::where('device_code_hash', $this->secrets->hash($deviceCode))->first();
        if (! $enrollment || ! $this->secrets->equals($deviceCode, (string) $enrollment->device_code_hash)) {
            throw new EnrollmentException('expired_token', 400, 'Device code expired.');
        }

        $result = DB::transaction(function () use ($enrollment): array|EnrollmentException {
            $enrollment = NodeEnrollment::lockForUpdate()->findOrFail($enrollment->id);
            if ($enrollment->expires_at->isPast()) {
                $enrollment->update(['status' => NodeEnrollmentStatus::Expired, 'last_activity_at' => now()]);

                return new EnrollmentException('expired_token', 400, 'Device code expired.');
            }
            if ($enrollment->last_polled_at && $enrollment->last_polled_at->addSeconds($enrollment->poll_interval)->isFuture()) {
                $enrollment->update(['poll_interval' => min(30, $enrollment->poll_interval + 5), 'last_activity_at' => now()]);

                return new EnrollmentException('slow_down', 429, 'Polling interval increased.', $enrollment->poll_interval);
            }
            $enrollment->update(['last_polled_at' => now(), 'last_activity_at' => now()]);
            if ($enrollment->status === NodeEnrollmentStatus::Denied) {
                return new EnrollmentException('access_denied', 400, 'Enrollment was denied.');
            }
            if ($enrollment->status === NodeEnrollmentStatus::Revoked) {
                return new EnrollmentException('enrollment_revoked', 400, 'Enrollment was revoked.');
            }
            if ($enrollment->status !== NodeEnrollmentStatus::Approved) {
                return new EnrollmentException('authorization_pending', 400, 'Authorization is pending.');
            }
            if ($enrollment->bootstrap_token_delivered_at) {
                return new EnrollmentException('token_consumed', 409, 'Bootstrap token was already delivered.');
            }

            return $this->deliverBootstrap($enrollment);
        });
        if ($result instanceof EnrollmentException) {
            throw $result;
        }

        return $result;
    }

    public function createAutomatic(array $configuration): array
    {
        return DB::transaction(function () use ($configuration): array {
            $token = $this->secrets->token();
            $agentID = (string) Str::uuid();
            $node = Node::create([
                'uuid' => (string) Str::uuid(),
                'agent_node_id' => $agentID,
                'name' => $configuration['name'],
                'endpoint' => rtrim($configuration['agent_endpoint'], '/'),
                'region' => $configuration['region'] ?? null,
                'environment' => $configuration['environment'],
                'status' => NodeStatus::Provisioning,
                'scheduling_enabled' => false,
                'maintenance' => $configuration['initial_maintenance'] ?? false,
                'agent_version' => $configuration['agent_version'],
                'agent_protocol_version' => '1',
            ]);
            $enrollment = NodeEnrollment::create([
                'uuid' => (string) Str::uuid(),
                'node_id' => $node->id,
                'preauthorization_token_hash' => $this->secrets->hash($token),
                'status' => NodeEnrollmentStatus::Approved,
                'mode' => NodeEnrollmentMode::Automatic,
                'chosen_name' => $configuration['name'],
                'region' => $configuration['region'] ?? null,
                'environment' => $configuration['environment'],
                'agent_fqdn' => $configuration['agent_fqdn'],
                'agent_endpoint' => rtrim($configuration['agent_endpoint'], '/'),
                'published_address' => $configuration['published_address'] ?? null,
                'agent_channel' => $configuration['agent_channel'],
                'requested_agent_version' => $configuration['agent_version'],
                'allowed_source_cidrs' => $configuration['allowed_source_cidrs'],
                'allowed_client_sans' => config('centralcloud.enrollment.allowed_client_sans'),
                'initial_maintenance' => $configuration['initial_maintenance'] ?? false,
                'maximum_deployments' => $configuration['maximum_deployments'] ?? null,
                'step' => NodeEnrollmentStep::Preflight,
                'correlation_id' => (string) Str::uuid(),
                'expires_at' => now()->addSeconds((int) config('centralcloud.enrollment.ttl')),
                'approved_at' => now(),
                'last_activity_at' => now(),
            ]);
            $this->audit->record('node_enrollment.automatic_token_created', $enrollment, ['node_id' => $node->uuid]);

            return ['enrollment' => $enrollment, 'token' => $token];
        });
    }

    public function exchangeAutomatic(string $token, array $metadata): array
    {
        $enrollment = NodeEnrollment::where('preauthorization_token_hash', $this->secrets->hash($token))->first();
        if (! $enrollment || ! $this->secrets->equals($token, (string) $enrollment->preauthorization_token_hash)) {
            throw new EnrollmentException('invalid_token', 401, 'Automatic enrollment token is invalid.');
        }

        return DB::transaction(function () use ($enrollment, $metadata): array {
            $enrollment = NodeEnrollment::lockForUpdate()->findOrFail($enrollment->id);
            if ($enrollment->expires_at->isPast()) {
                $enrollment->update(['status' => NodeEnrollmentStatus::Expired, 'preauthorization_token_hash' => null]);
                throw new EnrollmentException('expired_token', 410, 'Automatic enrollment token expired.');
            }
            if ($enrollment->bootstrap_token_delivered_at || ! $enrollment->preauthorization_token_hash) {
                throw new EnrollmentException('token_consumed', 409, 'Automatic enrollment token was already consumed.');
            }
            $enrollment->update([
                ...$this->metadata($metadata),
                'preauthorization_token_hash' => null,
                'last_activity_at' => now(),
            ]);
            $enrollment->node->update([
                'installer_version' => $metadata['installer_version'],
                'memory_total_bytes' => $metadata['memory_bytes'],
                'memory_available_bytes' => $metadata['memory_bytes'],
                'disk_total_bytes' => $metadata['disk_bytes'],
                'disk_available_bytes' => $metadata['disk_bytes'],
            ]);

            return $this->deliverBootstrap($enrollment);
        });
    }

    public function claim(string $userCode, int $userId): NodeEnrollment
    {
        $hash = $this->secrets->hashUserCode($userCode);
        $enrollment = NodeEnrollment::where('user_code_hash', $hash)->whereIn('status', [
            NodeEnrollmentStatus::PendingClaim, NodeEnrollmentStatus::AwaitingApproval,
        ])->first();
        if (! $enrollment || $enrollment->expires_at->isPast()) {
            throw new EnrollmentException('invalid_user_code', 422, 'Association code is invalid or expired.');
        }
        $enrollment->update([
            'status' => NodeEnrollmentStatus::AwaitingApproval,
            'claimed_by' => $userId,
            'claimed_at' => $enrollment->claimed_at ?: now(),
            'last_activity_at' => now(),
        ]);

        return $enrollment;
    }

    public function approve(NodeEnrollment $enrollment, array $configuration): NodeEnrollment
    {
        return DB::transaction(function () use ($enrollment, $configuration): NodeEnrollment {
            $enrollment = NodeEnrollment::lockForUpdate()->findOrFail($enrollment->id);
            if ($enrollment->expires_at->isPast()) {
                throw new EnrollmentException('expired_token', 410, 'Enrollment expired.');
            }
            if (! in_array($enrollment->status, [NodeEnrollmentStatus::PendingClaim, NodeEnrollmentStatus::AwaitingApproval], true)) {
                throw new EnrollmentException('invalid_state', 409, 'Enrollment cannot be approved in its current state.');
            }
            $agentID = (string) Str::uuid();
            $node = Node::create([
                'uuid' => (string) Str::uuid(),
                'agent_node_id' => $agentID,
                'name' => $configuration['name'],
                'endpoint' => rtrim($configuration['agent_endpoint'], '/'),
                'region' => $configuration['region'] ?? null,
                'environment' => $configuration['environment'],
                'status' => NodeStatus::Provisioning,
                'scheduling_enabled' => false,
                'maintenance' => $configuration['initial_maintenance'] ?? false,
                'agent_version' => $configuration['agent_version'],
                'agent_protocol_version' => '1',
                'installer_version' => $enrollment->installer_version,
                'installation_step' => $enrollment->step->value,
                'memory_total_bytes' => $enrollment->memory_bytes ?? 0,
                'memory_available_bytes' => $enrollment->memory_bytes ?? 0,
                'disk_total_bytes' => $enrollment->disk_bytes ?? 0,
                'disk_available_bytes' => $enrollment->disk_bytes ?? 0,
            ]);
            $enrollment->update([
                'node_id' => $node->id,
                'status' => NodeEnrollmentStatus::Approved,
                'chosen_name' => $configuration['name'],
                'region' => $configuration['region'] ?? null,
                'environment' => $configuration['environment'],
                'agent_fqdn' => $configuration['agent_fqdn'],
                'agent_endpoint' => rtrim($configuration['agent_endpoint'], '/'),
                'published_address' => $configuration['published_address'] ?? null,
                'agent_channel' => $configuration['agent_channel'],
                'requested_agent_version' => $configuration['agent_version'],
                'allowed_source_cidrs' => $configuration['allowed_source_cidrs'],
                'allowed_client_sans' => config('centralcloud.enrollment.allowed_client_sans'),
                'initial_maintenance' => $configuration['initial_maintenance'] ?? false,
                'maximum_deployments' => $configuration['maximum_deployments'] ?? null,
                'approved_at' => now(),
                'last_activity_at' => now(),
            ]);
            $this->audit->record('node_enrollment.approved', $enrollment, ['node_id' => $node->uuid]);

            return $enrollment->fresh(['node']);
        });
    }

    public function deny(NodeEnrollment $enrollment): void
    {
        $enrollment->update(['status' => NodeEnrollmentStatus::Denied, 'denied_at' => now(), 'last_activity_at' => now()]);
        $this->audit->record('node_enrollment.denied', $enrollment);
    }

    public function revoke(NodeEnrollment $enrollment): void
    {
        $enrollment->update(['status' => NodeEnrollmentStatus::Revoked, 'revoked_at' => now(), 'bootstrap_token_hash' => null, 'last_activity_at' => now()]);
        $enrollment->node?->update(['status' => NodeStatus::Offline, 'scheduling_enabled' => false]);
        $this->audit->record('node_enrollment.revoked', $enrollment);
    }

    public function progress(NodeEnrollment $enrollment, string $token, string $key, array $payload): array
    {
        return $this->withBootstrap($enrollment, $token, function (NodeEnrollment $locked) use ($key, $payload): array {
            if ($replay = $this->idempotency->replay($locked, 'progress', $key, $payload)) {
                return $replay;
            }
            $step = NodeEnrollmentStep::from($payload['step']);
            if ($step->order() < $locked->step->order() || ($step === $locked->step && $payload['percentage'] < $locked->percentage)) {
                throw new EnrollmentException('progress_out_of_order', 409, 'Progress cannot move backwards.');
            }
            $status = $step === NodeEnrollmentStep::Validation ? NodeEnrollmentStatus::Validating : NodeEnrollmentStatus::Installing;
            $locked->update([
                'status' => $status,
                'step' => $step,
                'percentage' => $payload['percentage'],
                'public_message' => $this->sanitize($payload['message']),
                'error_code' => $payload['error_code'] ?? null,
                'sanitized_error' => isset($payload['error_message']) ? $this->sanitize($payload['error_message']) : null,
                'last_activity_at' => now(),
            ]);
            $locked->node?->update(['status' => $status === NodeEnrollmentStatus::Validating ? NodeStatus::Validating : NodeStatus::Provisioning, 'installation_step' => $step->value]);
            $this->audit->record('node_enrollment.progress', $locked, ['step' => $step->value, 'percentage' => $payload['percentage'], 'error_code' => $payload['error_code'] ?? null]);

            return $this->idempotency->store($locked, 'progress', $key, $payload, 202, ['status' => 'accepted']);
        });
    }

    public function certificate(NodeEnrollment $enrollment, string $token, string $key, array $payload): array
    {
        return $this->withBootstrap($enrollment, $token, function (NodeEnrollment $locked) use ($key, $payload): array {
            if ($replay = $this->idempotency->replay($locked, 'certificate', $key, $payload)) {
                return $replay;
            }
            $csrHash = hash('sha256', $payload['csr']);
            if ($locked->csr_hash && ! hash_equals($locked->csr_hash, $csrHash)) {
                throw new EnrollmentException('certificate_already_issued', 409, 'A certificate was already issued for another CSR.');
            }
            if ($locked->csr_hash) {
                throw new EnrollmentException('certificate_already_issued', 409, 'A certificate was already issued. Replay the original idempotency key.');
            }
            try {
                $issued = $this->issuer->issue($locked->loadMissing('node'), $payload['csr']);
            } catch (\Throwable $exception) {
                throw new EnrollmentException(str_starts_with($exception->getMessage(), 'invalid_csr') ? $exception->getMessage() : 'certificate_issuer_failed', 422, 'CSR could not be issued.');
            }
            $locked->update([
                'csr_hash' => $csrHash,
                'issued_certificate' => $issued->certificate,
                'issued_chain' => $issued->chain,
                'certificate_serial' => $issued->serial,
                'certificate_issued_at' => now(),
                'last_activity_at' => now(),
            ]);
            $this->audit->record('node_enrollment.certificate_issued', $locked, ['serial' => $issued->serial, 'csr_sha256' => $csrHash]);
            $response = [
                'certificate' => $issued->certificate,
                'chain' => $issued->chain,
                'client_ca' => $issued->clientCa,
                'allowed_client_sans' => $locked->allowed_client_sans,
                'allowed_source_cidrs' => $locked->allowed_source_cidrs,
                'expires_at' => $issued->expiresAt->toIso8601String(),
            ];

            return $this->idempotency->store($locked, 'certificate', $key, $payload, 200, $response);
        });
    }

    public function complete(NodeEnrollment $enrollment, string $token, string $key, array $payload): array
    {
        return $this->withBootstrap($enrollment, $token, function (NodeEnrollment $locked) use ($key, $payload): array {
            if ($replay = $this->idempotency->replay($locked, 'complete', $key, $payload)) {
                return $replay;
            }
            if ($locked->finalized_at) {
                throw new EnrollmentException('completion_already_recorded', 409, 'Enrollment was already finalized. Replay the original idempotency key.');
            }
            if ($payload['agent_identity'] !== $locked->node->agent_node_id ||
                $payload['agent_version'] !== $locked->requested_agent_version ||
                $payload['protocol_version'] !== '1') {
                throw new EnrollmentException('incompatible_component', 409, 'Agent identity or version is incompatible.');
            }
            $requiredServices = ['docker', 'postgresql', 'traefik', 'agent'];
            $services = $payload['services'] ?? [];
            $validations = $payload['validations'] ?? [];
            $resources = $payload['resources'] ?? [];
            if (array_diff($requiredServices, array_keys($services)) !== [] ||
                array_filter($services, static fn (mixed $status): bool => $status !== 'ok') !== [] ||
                ($payload['healthcheck'] ?? null) !== 'ok' ||
                $validations === [] ||
                array_filter($validations, static fn (mixed $validation): bool => ! is_array($validation) || ($validation['status'] ?? null) !== 'ok') !== [] ||
                ! is_numeric($resources['memory_bytes'] ?? null) || (int) $resources['memory_bytes'] < 1 ||
                ! is_numeric($resources['disk_bytes'] ?? null) || (int) $resources['disk_bytes'] < 1) {
                throw new EnrollmentException('validation_failed', 422, 'Installer validation report is incomplete or contains failures.');
            }
            $locked->update([
                'status' => NodeEnrollmentStatus::Validating,
                'step' => NodeEnrollmentStep::Complete,
                'percentage' => 100,
                'completion_report' => $payload,
                'last_activity_at' => now(),
            ]);
            $locked->node->update(['status' => NodeStatus::Validating, 'installation_step' => NodeEnrollmentStep::Complete->value]);
            $this->audit->record('node_enrollment.completed', $locked, ['agent_version' => $payload['agent_version'], 'protocol_version' => $payload['protocol_version']]);
            $ready = $this->validateAgent($locked);
            $response = [
                'status' => $ready ? 'ready' : 'validating',
                'node_id' => $locked->node->uuid,
                'dashboard_url' => route('admin.nodes.show', $locked->node),
            ];

            return $this->idempotency->store($locked, 'complete', $key, $payload, 200, $response);
        });
    }

    public function validateAgent(NodeEnrollment $enrollment): bool
    {
        try {
            $ready = $this->agent->ready($enrollment->node);
            $resources = $this->agent->resources($enrollment->node);
            if (($ready['node_id'] ?? null) !== $enrollment->node->agent_node_id ||
                ($resources['node_id'] ?? null) !== $enrollment->node->agent_node_id ||
                ($ready['agent_version'] ?? null) !== $enrollment->requested_agent_version ||
                ($ready['protocol_version'] ?? null) !== '1' ||
                ($ready['status'] ?? null) !== 'ready') {
                throw new \RuntimeException('agent_identity_mismatch');
            }
            $enrollment->node->update([
                'status' => NodeStatus::Ready,
                'agent_version' => $ready['agent_version'],
                'agent_protocol_version' => $ready['protocol_version'],
                'cpu_count' => $resources['cpu_count'] ?? 0,
                'memory_total_bytes' => $resources['memory_total_bytes'] ?? 0,
                'memory_available_bytes' => $resources['memory_available_bytes'] ?? 0,
                'disk_total_bytes' => $resources['disk_total_bytes'] ?? 0,
                'disk_available_bytes' => $resources['disk_available_bytes'] ?? 0,
                'last_seen_at' => now(),
                'installed_at' => now(),
                'last_error_code' => null,
            ]);
            $enrollment->update(['status' => NodeEnrollmentStatus::Ready, 'finalized_at' => now(), 'last_activity_at' => now()]);
            $this->audit->record('node_enrollment.ready', $enrollment, ['node_id' => $enrollment->node->uuid]);

            return true;
        } catch (\Throwable) {
            $enrollment->update(['status' => NodeEnrollmentStatus::Validating, 'error_code' => 'agent_unreachable', 'sanitized_error' => 'Le Dashboard ne peut pas encore valider l’Agent.', 'last_activity_at' => now()]);

            return false;
        }
    }

    private function deliverBootstrap(NodeEnrollment $enrollment): array
    {
        $token = $this->secrets->token();
        $enrollment->update([
            'bootstrap_token_hash' => $this->secrets->hash($token),
            'bootstrap_expires_at' => now()->addSeconds((int) config('centralcloud.enrollment.bootstrap_ttl')),
            'bootstrap_token_delivered_at' => now(),
            'last_activity_at' => now(),
        ]);

        return [
            'status' => 'approved',
            'enrollment_id' => $enrollment->uuid,
            'bootstrap_token' => $token,
            'bootstrap_expires_in' => (int) config('centralcloud.enrollment.bootstrap_ttl'),
            'node' => [
                'id' => $enrollment->node->agent_node_id,
                'name' => $enrollment->chosen_name,
                'fqdn' => $enrollment->agent_fqdn,
                'endpoint' => $enrollment->agent_endpoint,
                'environment' => $enrollment->environment,
                'region' => $enrollment->region,
                'panel_domain_suffix' => config('centralcloud.panel.domain_suffix'),
            ],
            'agent' => [
                'version' => $enrollment->requested_agent_version,
                'channel' => $enrollment->agent_channel,
                'protocol_version' => '1',
                'manifest_url' => config('centralcloud.enrollment.agent_manifest_url'),
            ],
            'security' => [
                'allowed_client_sans' => $enrollment->allowed_client_sans,
                'allowed_source_cidrs' => $enrollment->allowed_source_cidrs,
            ],
        ];
    }

    private function withBootstrap(NodeEnrollment $enrollment, string $token, callable $callback): array
    {
        return DB::transaction(function () use ($enrollment, $token, $callback): array {
            $locked = NodeEnrollment::with('node')->lockForUpdate()->findOrFail($enrollment->id);
            if (! $locked->bootstrap_token_hash || ! $this->secrets->equals($token, $locked->bootstrap_token_hash) ||
                ! $locked->bootstrap_expires_at || $locked->bootstrap_expires_at->isPast() ||
                $locked->status === NodeEnrollmentStatus::Revoked) {
                throw new EnrollmentException('invalid_token', 401, 'Bootstrap token is invalid or expired.');
            }

            return $callback($locked);
        });
    }

    private function metadata(array $metadata): array
    {
        return [
            'hostname' => $metadata['hostname'],
            'os' => $metadata['os'],
            'os_version' => $metadata['os_version'],
            'architecture' => $metadata['architecture'],
            'memory_bytes' => $metadata['memory_bytes'],
            'disk_bytes' => $metadata['disk_bytes'],
            'installer_version' => $metadata['installer_version'],
            'local_nonce' => $metadata['nonce'],
            'capabilities' => $metadata['capabilities'] ?? [],
            'ip_addresses' => $metadata['ip_addresses'] ?? [],
        ];
    }

    private function sanitize(string $message): string
    {
        $message = (string) preg_replace(
            '/(?i)\bauthorization\s*[:=]\s*(?:bearer\s+)?\S+/',
            'Authorization=[REDACTED]',
            $message,
        );
        $message = (string) preg_replace(
            '/(?i)\b(token|password|secret|private[_ .-]?key)\s*[:=]\s*(?:"[^"]*"|\S+)/',
            '$1=[REDACTED]',
            $message,
        );
        $message = (string) preg_replace(
            '/-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----.*?-----END [A-Z0-9 ]*PRIVATE KEY-----/is',
            '[REDACTED PRIVATE KEY]',
            $message,
        );

        return mb_substr($message, 0, 1000);
    }
}
