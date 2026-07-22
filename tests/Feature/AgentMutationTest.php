<?php

namespace Tests\Feature;

use App\Models\AgentOperation;
use App\Models\AgentRequest;
use App\Models\Deployment;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Services\AgentMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutation_has_required_headers_and_discards_accepted_secret_payload(): void
    {
        [$deployment] = $this->fixture();
        Http::fake(['https://node.example/*' => Http::response(['operation_id' => (string) Str::uuid(), 'deployment_id' => $deployment->uuid, 'status' => 'queued'], 202)]);
        $op = app(AgentMutationService::class)->dispatch($deployment, 'admin_reset', 'POST', "/v1/deployments/{$deployment->uuid}/admin-reset", ['admin_email' => 'owner@example.com', 'admin_password' => 'a-very-long-secret-password']);
        Http::assertSent(fn (Request $r) => Str::isUuid($r->header('Idempotency-Key')[0] ?? '') && Str::isUuid($r->header('X-Correlation-ID')[0] ?? '') && filled($r->header('X-Request-Timestamp')[0] ?? null));
        $this->assertNull($op->request->encrypted_payload);
        $this->assertDatabaseMissing('audit_logs', ['metadata' => 'a-very-long-secret-password']);
    }

    public function test_local_concurrent_mutation_is_rejected(): void
    {
        [$deployment] = $this->fixture();
        AgentOperation::create(['uuid' => (string) Str::uuid(), 'deployment_id' => $deployment->id, 'node_id' => $deployment->node_id, 'type' => 'start', 'status' => 'RUNNING', 'idempotency_key' => (string) Str::uuid(), 'correlation_id' => (string) Str::uuid()]);
        $this->expectException(\DomainException::class);
        app(AgentMutationService::class)->dispatch($deployment, 'stop', 'POST', "/v1/deployments/{$deployment->uuid}/stop");
    }

    public function test_retry_preserves_empty_json_objects_in_the_payload(): void
    {
        [$deployment] = $this->fixture();
        $operation = AgentOperation::create(['uuid' => (string) Str::uuid(), 'deployment_id' => $deployment->id, 'node_id' => $deployment->node_id, 'type' => 'create', 'status' => 'QUEUED', 'idempotency_key' => (string) Str::uuid(), 'correlation_id' => (string) Str::uuid()]);
        AgentRequest::create(['agent_operation_id' => $operation->id, 'deployment_id' => $deployment->id, 'node_id' => $deployment->node_id, 'idempotency_key' => $operation->idempotency_key, 'correlation_id' => $operation->correlation_id, 'method' => 'POST', 'path' => '/v1/deployments', 'request_hash' => hash('sha256', 'test'), 'encrypted_payload' => Crypt::encryptString('{"environment":{}}'), 'state' => 'PENDING', 'attempts' => 1]);
        Http::fake(['https://node.example/*' => Http::response(['operation_id' => (string) Str::uuid(), 'deployment_id' => $deployment->uuid, 'status' => 'queued'], 202)]);

        $this->artisan('centralcloud:requests:retry')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_contains($request->body(), '"environment":{}'));
        $this->assertSame('ACCEPTED', $operation->request->fresh()->state);
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $plan = Plan::create(['uuid' => (string) Str::uuid(), 'name' => 'Standard', 'slug' => 'standard', 'active' => true, 'price' => 1000, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 536870912, 'cpu_limit' => .5]);
        $project = Project::create(['uuid' => (string) Str::uuid(), 'owner_id' => $user->id, 'plan_id' => $plan->id, 'name' => 'Panel', 'status' => 'ACTIVE']);
        $node = Node::create(['uuid' => (string) Str::uuid(), 'agent_node_id' => (string) Str::uuid(), 'name' => 'Node', 'endpoint' => 'https://node.example', 'status' => 'ONLINE', 'scheduling_enabled' => true, 'last_seen_at' => now()]);
        $deployment = Deployment::create(['uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'node_id' => $node->id, 'hostname' => 'abc.cloud.centralcorp.fr', 'state' => 'active', 'desired_state' => 'active', 'memory_bytes' => $plan->memory_bytes, 'cpu_limit' => $plan->cpu_limit, 'image_reference' => 'ghcr.io/centralcorp/centralpanel@sha256:'.str_repeat('a', 64)]);

        return [$deployment];
    }
}
