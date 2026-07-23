<?php

namespace App\Services\NodeAgent;

use App\Exceptions\NodeAgentException;
use App\Models\Node;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class NodeAgentClient
{
    public function health(Node $node): array
    {
        return $this->request($node, 'GET', '/v1/health')->json();
    }

    public function resources(Node $node): array
    {
        return $this->request($node, 'GET', '/v1/resources')->json();
    }

    public function ready(Node $node): array
    {
        return $this->request($node, 'GET', '/v1/ready')->json();
    }

    public function deployments(Node $node): array
    {
        return $this->request($node, 'GET', '/v1/deployments')->json();
    }

    public function deployment(Node $node, string $id): array
    {
        return $this->request($node, 'GET', "/v1/deployments/{$id}")->json();
    }

    public function operation(Node $node, string $id): array
    {
        return $this->request($node, 'GET', "/v1/operations/{$id}")->json();
    }

    public function logs(Node $node, string $id, int $limit = 100, ?string $cursor = null): array
    {
        return $this->request($node, 'GET', "/v1/deployments/{$id}/logs", query: array_filter(['limit' => $limit, 'cursor' => $cursor]))->json();
    }

    public function mutate(Node $node, string $method, string $path, ?array $body, string $idempotencyKey, string $correlationId, array $extraHeaders = []): array
    {
        return $this->request($node, $method, $path, $body, ['Idempotency-Key' => $idempotencyKey, 'X-Correlation-ID' => $correlationId, 'X-Request-Timestamp' => now('UTC')->toRfc3339String(), ...$extraHeaders])->json();
    }

    public function newMutationIdentity(): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'correlation_id' => (string) Str::uuid()];
    }

    private function request(Node $node, string $method, string $path, ?array $body = null, array $headers = [], array $query = []): Response
    {
        $request = $this->pending($node)->baseUrl(rtrim($node->endpoint, '/'))->withHeaders($headers);
        if ($query) {
            $path .= '?'.http_build_query($query);
        }
        $response = $body === null ? $request->send($method, $path) : $request->withBody(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'application/json')->send($method, $path);
        if ($response->successful()) {
            return $response;
        }
        $fallbackCode = match ($response->status()) {
            409 => 'conflict',
            429 => 'rate_limited',
            503 => 'degraded',
            default => 'internal_error',
        };
        $code = (string) $response->json('error.code', $fallbackCode);
        $retryAfter = filter_var($response->header('Retry-After'), FILTER_VALIDATE_INT);
        throw new NodeAgentException(
            $code,
            $response->json('error.correlation_id') ?: $response->header('X-Correlation-ID'),
            $response->status(),
            retryAfter: $retryAfter === false ? null : $retryAfter,
        );
    }

    private function pending(Node $node): PendingRequest
    {
        if ($node->agent_auth_mode === 'bearer') {
            if (! is_string($node->agent_token) || strlen($node->agent_token) < 32) {
                throw new NodeAgentException('agent_auth_not_configured', null, 500, 'Agent bearer authentication is not configured');
            }

            return Http::acceptJson()
                ->withToken($node->agent_token)
                ->connectTimeout(config('centralcloud.agent.connect_timeout'))
                ->timeout(config('centralcloud.agent.timeout'))
                ->retry(0, 0)
                ->withOptions(['verify' => true, 'allow_redirects' => false]);
        }

        // Legacy nodes keep using the former global mTLS credentials until they
        // are explicitly re-enrolled or rotated to per-node bearer auth.
        $cert = config('centralcloud.agent.client_cert');
        $key = config('centralcloud.agent.client_key');
        $ca = config('centralcloud.agent.ca_cert');
        if (app()->environment('production') && (! $cert || ! $key || ! $ca)) {
            throw new NodeAgentException('mtls_not_configured', null, 500, 'Agent mTLS is not configured');
        }

        return Http::acceptJson()->connectTimeout(config('centralcloud.agent.connect_timeout'))->timeout(config('centralcloud.agent.timeout'))->retry(0, 0)->withOptions(array_filter(['cert' => $cert, 'ssl_key' => $key, 'verify' => $ca ?: true, 'allow_redirects' => false]));
    }
}
