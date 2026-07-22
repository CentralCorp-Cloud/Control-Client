<?php

namespace Tests\Feature;

use App\Models\AgentRequest;
use App\Models\Deployment;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Services\DeploymentPurgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_token_remains_backend_only_and_is_removed_after_agent_acceptance(): void
    {
        $deployment = $this->deployment();
        $operationId = (string) Str::uuid();
        Http::fake(function (Request $request) use ($operationId) {
            return str_contains($request->url(), 'purge-token') ? Http::response(['purge_token' => 'one-time-purge-secret', 'expires_at' => now()->addMinutes(5)->toRfc3339String()], 201) : Http::response(['operation_id' => $operationId, 'deployment_id' => 'ignored', 'status' => 'queued'], 202);
        });
        $operation = app(DeploymentPurgeService::class)->purge($deployment);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'mode=purge') && ($r->header('X-Purge-Token')[0] ?? null) === 'one-time-purge-secret');
        $this->assertNull($operation->request->encrypted_headers);
        $serialized = implode('', AgentRequest::pluck('encrypted_headers')->filter()->all());
        $this->assertStringNotContainsString('one-time-purge-secret', $serialized);
    }

    public function test_uncertain_purge_token_request_reuses_its_idempotency_key(): void
    {
        $deployment = $this->deployment();
        $calls = 0;
        $keys = [];
        Http::fake(function (Request $request) use (&$calls, &$keys) {
            $calls++;
            if (str_contains($request->url(), 'purge-token')) {
                $keys[] = $request->header('Idempotency-Key')[0] ?? null;
                if (count($keys) === 1) {
                    throw new ConnectionException('timeout');
                }

                return Http::response(['purge_token' => 'one-time-token'], 201);
            }

            return Http::response(['operation_id' => (string) Str::uuid(), 'status' => 'queued'], 202);
        });
        try {
            app(DeploymentPurgeService::class)->purge($deployment);
        } catch (ConnectionException) {
        }
        app(DeploymentPurgeService::class)->purge($deployment);

        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
        $this->assertDatabaseHas('agent_requests', ['path' => "/v1/deployments/{$deployment->uuid}/purge-token", 'attempts' => 2, 'state' => 'ACCEPTED']);
    }

    public function test_purge_requires_the_exact_project_name(): void
    {
        $deployment = $this->deployment();
        $user = $deployment->project->owner;

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->delete(route('deployments.purge', $deployment->uuid), ['confirmation' => 'SUPPRIMER']);

        $response->assertSessionHasErrors('confirmation');
        $this->assertDatabaseMissing('agent_operations', ['deployment_id' => $deployment->id, 'type' => 'delete_purge']);
    }

    public function test_purge_rate_limit_allows_five_attempts_and_returns_a_clear_429_page(): void
    {
        $deployment = $this->deployment();
        $user = $deployment->project->owner;
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->unix()]);

        foreach (range(1, 5) as $attempt) {
            $this->delete(route('deployments.purge', $deployment->uuid), ['confirmation' => 'incorrect-'.$attempt])
                ->assertSessionHasErrors('confirmation');
        }

        $this->delete(route('deployments.purge', $deployment->uuid), ['confirmation' => 'encore-incorrect'])
            ->assertStatus(429)
            ->assertSee('Trop de tentatives rapprochées');
    }

    public function test_purge_rate_limit_is_isolated_per_deployment(): void
    {
        $first = $this->deployment();
        $user = $first->project->owner;
        $second = $this->deployment($user);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->unix()]);

        foreach (range(1, 5) as $attempt) {
            $this->delete(route('deployments.purge', $first->uuid), ['confirmation' => 'incorrect-'.$attempt]);
        }

        $this->delete(route('deployments.purge', $second->uuid), ['confirmation' => 'incorrect'])
            ->assertSessionHasErrors('confirmation');
    }

    private function deployment(?User $u = null): Deployment
    {
        $u ??= User::factory()->create();
        $planSlug = 'p-'.Str::lower(Str::random(8));
        $p = Plan::create(['uuid' => (string) Str::uuid(), 'name' => $planSlug, 'slug' => $planSlug, 'active' => true, 'price' => 1, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 536870912, 'cpu_limit' => .5]);
        $project = Project::create(['uuid' => (string) Str::uuid(), 'owner_id' => $u->id, 'plan_id' => $p->id, 'name' => 'Panel', 'status' => 'ACTIVE']);
        $node = Node::create(['uuid' => (string) Str::uuid(), 'agent_node_id' => (string) Str::uuid(), 'name' => 'N', 'endpoint' => 'https://node.example', 'status' => 'ONLINE']);

        return Deployment::create(['uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'node_id' => $node->id, 'hostname' => Str::lower(Str::random(10)).'.cloud.centralcorp.fr', 'state' => 'active', 'desired_state' => 'active', 'memory_bytes' => $p->memory_bytes, 'cpu_limit' => $p->cpu_limit, 'image_reference' => 'image']);
    }
}
