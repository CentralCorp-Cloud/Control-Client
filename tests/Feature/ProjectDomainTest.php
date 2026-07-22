<?php

namespace Tests\Feature;

use App\Contracts\CnameResolver;
use App\Enums\DomainMode;
use App\Enums\ProjectStatus;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProvisioningRequest;
use App\Models\StripeEvent;
use App\Models\User;
use App\Services\Billing\StripeEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_rejects_a_custom_domain(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan(free: true);

        $this->actingAs($user)->post(route('projects.store'), $this->payload($plan, [
            'domain_mode' => 'CUSTOM',
            'custom_url' => 'panel.example.com',
        ]))->assertSessionHasErrors('domain_mode');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_paid_project_normalizes_and_reserves_a_custom_hostname(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan();

        $response = $this->actingAs($user)->post(route('projects.store'), $this->payload($plan, [
            'domain_mode' => 'CUSTOM',
            'custom_url' => 'HTTPS://Panel.Example.COM/',
        ]));

        $project = $user->projects()->firstOrFail();
        $response->assertRedirect(route('projects.show', $project));
        $response->assertSessionHas('warning');
        $this->assertSame(DomainMode::Custom, $project->domain_mode);
        $this->assertSame('panel.example.com', $project->custom_hostname);
        $this->assertMatchesRegularExpression('/^panel-[a-f0-9]{20}\.'.preg_quote(config('centralcloud.panel.domain_suffix'), '/').'$/', $project->canonical_hostname);
    }

    public function test_centralcloud_hostname_must_be_available(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan();
        Project::create(['uuid' => (string) Str::uuid(), 'owner_id' => $user->id, 'plan_id' => $plan->id, 'name' => 'Existing', 'status' => ProjectStatus::PendingPayment, 'canonical_hostname' => 'monpanel.'.config('centralcloud.panel.domain_suffix')]);

        $this->actingAs($user)->post(route('projects.store'), $this->payload($plan, [
            'domain_mode' => 'CENTRALCLOUD',
            'central_subdomain' => 'MonPanel',
        ]))->assertSessionHasErrors('central_subdomain');

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_verified_cname_provisions_on_a_capable_node_with_the_alias(): void
    {
        config(['centralcloud.panel.image' => 'ghcr.io/centralcorp/centralpanel@sha256:'.str_repeat('a', 64)]);
        [$user, $project] = $this->pendingCustomProject();
        $this->capableNode();
        $this->fakeResolver($project->canonical_hostname);
        $operationId = (string) Str::uuid();
        Http::fake(['https://node.example/*' => Http::response(['operation_id' => $operationId, 'status' => 'queued'], 202)]);

        $this->actingAs($user)->post(route('projects.domain.verify', $project))->assertRedirect()->assertSessionHas('success');

        $project->refresh();
        $this->assertNotNull($project->domain_verified_at);
        $this->assertSame(ProjectStatus::Provisioning, $project->status);
        $this->assertSame($project->canonical_hostname, $project->deployment->hostname);
        Http::assertSent(function (Request $request) use ($project): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/v1/deployments')
                && ($data['hostname'] ?? null) === $project->canonical_hostname
                && ($data['aliases'] ?? null) === [$project->custom_hostname];
        });
    }

    public function test_missing_cname_keeps_project_pending_without_contacting_agent(): void
    {
        [$user, $project] = $this->pendingCustomProject();
        $this->fakeResolver(null);
        Http::fake();

        $this->actingAs($user)->post(route('projects.domain.verify', $project))->assertRedirect()->assertSessionHas('warning');

        $project->refresh();
        $this->assertSame(ProjectStatus::PendingDomain, $project->status);
        $this->assertNull($project->domain_verified_at);
        $this->assertNotNull($project->domain_last_checked_at);
        $this->assertNotNull($project->domain_check_error);
        $this->assertNull($project->deployment);
        Http::assertNothingSent();
    }

    public function test_paid_webhook_waits_for_custom_domain_verification(): void
    {
        [, $project] = $this->pendingCustomProject();
        $project->update(['status' => ProjectStatus::PendingPayment, 'payment_confirmed_at' => null]);
        $event = StripeEvent::create([
            'stripe_event_id' => 'evt_'.Str::random(12),
            'type' => 'checkout.session.completed',
            'payload' => ['data' => ['object' => ['payment_status' => 'paid', 'metadata' => ['project_uuid' => $project->uuid]]]],
            'status' => 'RECEIVED',
        ]);
        $this->fakeResolver(null);
        Http::fake();

        app(StripeEventProcessor::class)->process($event);

        $project->refresh();
        $this->assertSame(ProjectStatus::PendingDomain, $project->status);
        $this->assertNotNull($project->payment_confirmed_at);
        $this->assertNull($project->deployment);
        $this->assertSame('PROCESSED', $event->fresh()->status);
        Http::assertNothingSent();
    }

    private function pendingCustomProject(): array
    {
        $user = User::factory()->create();
        $plan = $this->plan();
        $project = Project::create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => 'Custom Panel',
            'status' => ProjectStatus::PendingDomain,
            'domain_mode' => DomainMode::Custom,
            'canonical_hostname' => 'panel-abc123.panels.centralcloud.fr',
            'custom_hostname' => 'panel.example.com',
            'payment_confirmed_at' => now(),
        ]);
        ProvisioningRequest::create([
            'project_id' => $project->id,
            'encrypted_bootstrap' => Crypt::encryptString(json_encode(['admin_name' => 'Alice', 'admin_email' => 'alice@example.test', 'admin_password' => 'Secret-password-123!'], JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addDay(),
        ]);

        return [$user, $project];
    }

    private function capableNode(): Node
    {
        return Node::create([
            'uuid' => (string) Str::uuid(), 'agent_node_id' => (string) Str::uuid(), 'name' => 'Node', 'endpoint' => 'https://node.example',
            'status' => 'ONLINE', 'scheduling_enabled' => true, 'maintenance' => false, 'capabilities' => ['hostname_aliases'],
            'memory_total_bytes' => 4294967296, 'memory_available_bytes' => 3221225472, 'disk_total_bytes' => 107374182400,
            'disk_available_bytes' => 53687091200, 'deployment_count' => 0, 'last_seen_at' => now(),
        ]);
    }

    private function fakeResolver(?string $target): void
    {
        $this->app->instance(CnameResolver::class, new class($target) implements CnameResolver
        {
            public function __construct(private ?string $target) {}

            public function resolve(string $hostname): ?string
            {
                return $this->target;
            }
        });
    }

    private function plan(bool $free = false): Plan
    {
        return Plan::create(['uuid' => (string) Str::uuid(), 'name' => $free ? 'Free' : 'Standard', 'slug' => $free ? 'free' : 'standard', 'active' => true, 'is_free' => $free, 'price' => $free ? 0 : 1990, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 536870912, 'cpu_limit' => .5]);
    }

    private function payload(Plan $plan, array $overrides = []): array
    {
        return [...[
            'name' => 'Mon Panel', 'plan_id' => $plan->id, 'domain_mode' => 'CENTRALCLOUD', 'central_subdomain' => 'mon-panel',
            'admin_email' => 'admin@example.test', 'admin_password' => 'Strong-password-123!', 'admin_password_confirmation' => 'Strong-password-123!',
        ], ...$overrides];
    }
}
