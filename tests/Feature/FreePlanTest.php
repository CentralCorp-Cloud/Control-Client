<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FreePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_bypasses_stripe_and_preserves_project_when_capacity_is_unavailable(): void
    {
        $user = User::factory()->create();
        $plan = $this->freePlan();
        Http::fake();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Mon CentralPanel gratuit',
            'plan_id' => $plan->id,
            'admin_email' => 'admin@example.test',
            'admin_password' => 'Strong-password-123!',
            'admin_password_confirmation' => 'Strong-password-123!',
        ]);

        $project = $user->projects()->firstOrFail();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame('FREE', $project->billing_type);
        $this->assertSame('PENDING_CAPACITY', $project->status->value);
        $this->assertNull($project->provisioningRequest->stripe_checkout_session_id);
        $this->assertNull($project->provisioningRequest->consumed_at);
        Http::assertNothingSent();
    }

    public function test_user_cannot_claim_two_active_free_projects(): void
    {
        $user = User::factory()->create();
        $plan = $this->freePlan();
        $payload = ['name' => 'Free Panel', 'plan_id' => $plan->id, 'admin_email' => 'admin@example.test', 'admin_password' => 'Strong-password-123!', 'admin_password_confirmation' => 'Strong-password-123!'];
        $this->actingAs($user)->post(route('projects.store'), $payload)->assertRedirect();

        $this->actingAs($user)->post(route('projects.store'), [...$payload, 'name' => 'Second Panel'])->assertSessionHasErrors('plan_id');
        $this->assertCount(1, $user->projects()->get());
    }

    public function test_admin_free_plan_forces_zero_price_and_removes_stripe_ids(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Free', 'slug' => 'free', 'description' => 'Free tier', 'is_free' => '1', 'active' => '1', 'price' => 999, 'currency' => 'EUR', 'billing_interval' => 'month', 'stripe_product_id' => 'prod_forbidden', 'stripe_price_id' => 'price_forbidden', 'memory_bytes' => 268435456, 'cpu_limit' => .25,
        ])->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseHas('plans', ['slug' => 'free', 'is_free' => true, 'price' => 0, 'stripe_product_id' => null, 'stripe_price_id' => null, 'maximum_projects' => 1]);
    }

    private function freePlan(): Plan
    {
        return Plan::create(['uuid' => (string) Str::uuid(), 'name' => 'Découverte', 'slug' => 'decouverte', 'active' => true, 'is_free' => true, 'price' => 0, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 268435456, 'cpu_limit' => .25, 'maximum_projects' => 1]);
    }
}
