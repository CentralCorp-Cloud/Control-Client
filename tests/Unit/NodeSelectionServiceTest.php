<?php

namespace Tests\Unit;

use App\Enums\DomainMode;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Services\NodeSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NodeSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_fresh_schedulable_online_node_with_capacity_is_selected(): void
    {
        $plan = Plan::create(['uuid' => (string) Str::uuid(), 'name' => 'P', 'slug' => 'p', 'active' => true, 'price' => 1, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 536870912, 'cpu_limit' => .5]);
        $good = $this->node('ONLINE', true, now(), 2147483648);
        $this->node('DEGRADED', true, now(), 8589934592);
        $this->node('ONLINE', false, now(), 8589934592);
        $this->node('ONLINE', true, now()->subHour(), 8589934592);
        $this->assertTrue(app(NodeSelectionService::class)->select($plan)->is($good));
    }

    public function test_custom_domain_requires_hostname_alias_capability(): void
    {
        $plan = Plan::create(['uuid' => (string) Str::uuid(), 'name' => 'P', 'slug' => 'p', 'active' => true, 'price' => 1, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 536870912, 'cpu_limit' => .5]);
        $user = User::factory()->create();
        $project = Project::create(['uuid' => (string) Str::uuid(), 'owner_id' => $user->id, 'plan_id' => $plan->id, 'name' => 'Custom', 'status' => 'PAYMENT_CONFIRMED', 'domain_mode' => DomainMode::Custom, 'canonical_hostname' => 'canonical.panels.centralcloud.fr', 'custom_hostname' => 'panel.example.com', 'domain_verified_at' => now()]);
        $this->node('ONLINE', true, now(), 2147483648);
        $capable = $this->node('ONLINE', true, now(), 2147483648);
        $capable->update(['capabilities' => ['hostname_aliases']]);

        $this->assertTrue(app(NodeSelectionService::class)->select($project)->is($capable));
    }

    private function node(string $status, bool $scheduling, $seen, int $available): Node
    {
        return Node::create(['uuid' => (string) Str::uuid(), 'agent_node_id' => (string) Str::uuid(), 'name' => Str::random(6), 'endpoint' => 'https://'.Str::random(6).'.example', 'status' => $status, 'scheduling_enabled' => $scheduling, 'memory_total_bytes' => 17179869184, 'memory_available_bytes' => $available, 'disk_total_bytes' => 107374182400, 'disk_available_bytes' => 53687091200, 'last_seen_at' => $seen]);
    }
}
