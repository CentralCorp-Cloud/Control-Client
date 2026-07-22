<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProvisioningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_checkout_provisions_and_operation_success_activates_project(): void
    {
        Notification::fake();
        [$project, $event] = $this->fixture();
        $operationId = (string) Str::uuid();
        Http::fake(function (Request $request) use ($operationId) {
            if (str_contains($request->url(), '/v1/operations/')) {
                return Http::response(['id' => $operationId, 'status' => 'succeeded'], 200);
            }

            return Http::response(['operation_id' => $operationId, 'status' => 'queued'], 202);
        });

        app(StripeEventProcessor::class)->process($event);
        $this->assertSame('PROVISIONING', $project->fresh()->status->value);
        $this->assertDatabaseHas('agent_operations', ['type' => 'create', 'status' => 'QUEUED', 'agent_operation_id' => $operationId]);
        $this->assertNotNull($project->fresh()->deployment);
        Http::assertSent(function (Request $request): bool {
            if (! str_ends_with($request->url(), '/v1/deployments')) {
                return false;
            }

            $payload = $request->data();

            return str_contains($request->body(), '"environment":{}')
                && ($payload['environment'] ?? null) === []
                && ! array_key_exists('APP_ENV', $payload['environment'] ?? [])
                && ! array_key_exists('CENTRALPANEL_MODE', $payload['environment'] ?? [])
                && ! array_key_exists('CLOUD_PROJECT_ID', $payload['environment'] ?? []);
        });

        $this->artisan('centralcloud:operations:poll')->assertSuccessful();
        $this->assertSame('ACTIVE', $project->fresh()->status->value);
        $this->assertSame('active', $project->fresh()->deployment->state);
    }

    public function test_capacity_rejection_preserves_paid_purchase_for_retry(): void
    {
        Notification::fake();
        [$project, $event] = $this->fixture();
        Http::fake(['https://node.example/*' => Http::response(['error' => ['code' => 'capacity_exceeded', 'correlation_id' => (string) Str::uuid()]], 409)]);

        app(StripeEventProcessor::class)->process($event);

        $this->assertSame('PROCESSED', $event->fresh()->status);
        $this->assertSame('PENDING_CAPACITY', $project->fresh()->status->value);
        $this->assertDatabaseHas('agent_operations', ['type' => 'create', 'status' => 'FAILED', 'error_code' => 'capacity_exceeded']);
        $this->assertDatabaseHas('incidents', ['source_type' => 'deployment', 'status' => 'OPEN']);
        $this->assertNull($project->fresh()->provisioningRequest->consumed_at);
    }

    private function fixture(): array
    {
        config(['centralcloud.panel.image' => 'ghcr.io/centralcorp/centralpanel@sha256:'.str_repeat('a', 64)]);
        $user = User::factory()->create();
        $plan = Plan::create(['uuid' => (string) Str::uuid(), 'name' => 'Standard', 'slug' => 'standard', 'active' => true, 'price' => 1990, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 536870912, 'cpu_limit' => .5]);
        $project = Project::create(['uuid' => (string) Str::uuid(), 'owner_id' => $user->id, 'plan_id' => $plan->id, 'name' => 'Launcher', 'status' => 'PENDING_PAYMENT']);
        ProvisioningRequest::create(['project_id' => $project->id, 'encrypted_bootstrap' => Crypt::encryptString(json_encode(['admin_name' => 'Alice', 'admin_email' => 'alice@example.test', 'admin_password' => 'Secret-password-123!'], JSON_THROW_ON_ERROR)), 'expires_at' => now()->addDay()]);
        Node::create(['uuid' => (string) Str::uuid(), 'agent_node_id' => (string) Str::uuid(), 'name' => 'Node', 'endpoint' => 'https://node.example', 'status' => 'ONLINE', 'scheduling_enabled' => true, 'maintenance' => false, 'memory_total_bytes' => 4294967296, 'memory_available_bytes' => 3221225472, 'disk_total_bytes' => 107374182400, 'disk_available_bytes' => 53687091200, 'deployment_count' => 0, 'last_seen_at' => now()]);
        $event = StripeEvent::create(['stripe_event_id' => 'evt_'.Str::random(12), 'type' => 'checkout.session.completed', 'payload' => ['data' => ['object' => ['payment_status' => 'paid', 'metadata' => ['project_uuid' => $project->uuid]]]], 'status' => 'RECEIVED']);

        return [$project, $event];
    }
}
