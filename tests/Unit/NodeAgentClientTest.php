<?php

namespace Tests\Unit;

use App\Exceptions\NodeAgentException;
use App\Models\Node;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class NodeAgentClientTest extends TestCase
{
    public function test_health_and_resources_are_server_side_requests(): void
    {
        $node = new Node(['endpoint' => 'https://node.example']);
        Http::fake(['https://node.example/v1/health' => Http::response(['node_id' => (string) Str::uuid(), 'status' => 'ok'])]);
        $this->assertSame('ok', app(NodeAgentClient::class)->health($node)['status']);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://node.example/v1/health');
    }

    public function test_capacity_error_is_mapped_without_exposing_agent_message(): void
    {
        $node = new Node(['endpoint' => 'https://node.example']);
        Http::fake(['*' => Http::response(['error' => ['code' => 'capacity_exceeded', 'message' => 'internal capacity details', 'correlation_id' => (string) Str::uuid()]], 409)]);
        try {
            app(NodeAgentClient::class)->mutate($node, 'POST', '/v1/deployments', [], (string) Str::uuid(), (string) Str::uuid());
            $this->fail('Exception expected');
        } catch (NodeAgentException $e) {
            $this->assertSame('capacity_exceeded', $e->agentCode);
            $this->assertStringNotContainsString('internal capacity details', $e->clientMessage());
        }
    }

    public function test_http_429_is_mapped_to_a_retryable_rate_limit_error(): void
    {
        $node = new Node(['endpoint' => 'https://node.example']);
        Http::fake(['*' => Http::response([], 429, ['Retry-After' => '90'])]);

        try {
            app(NodeAgentClient::class)->mutate($node, 'DELETE', '/v1/deployments/example', null, (string) Str::uuid(), (string) Str::uuid());
            $this->fail('Exception expected');
        } catch (NodeAgentException $exception) {
            $this->assertSame('rate_limited', $exception->agentCode);
            $this->assertSame(429, $exception->httpStatus);
            $this->assertSame(90, $exception->retryAfter);
            $this->assertStringContainsString('temporairement', $exception->clientMessage());
        }
    }
}
