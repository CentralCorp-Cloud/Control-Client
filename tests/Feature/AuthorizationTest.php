<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_another_users_project(): void
    {
        [$a, , $project] = $this->fixture();
        $this->actingAs($a)->get(route('projects.show', $project->uuid))->assertForbidden();
    }

    public function test_owner_can_view_their_project(): void
    {
        [, $b, $project] = $this->fixture();
        $this->actingAs($b)->get(route('projects.show', $project->uuid))->assertOk();
    }

    public function test_regular_user_cannot_access_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_must_enable_two_factor_authentication(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('security.index'));
        $admin->forceFill(['two_factor_secret' => encrypt('totp-secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin->refresh())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_unverified_user_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'SUSPENDED', 'password' => 'correct-password']);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_cannot_fetch_another_projects_logs(): void
    {
        [$a, , $project] = $this->fixture();
        $node = Node::create(['uuid' => (string) Str::uuid(), 'agent_node_id' => (string) Str::uuid(), 'name' => 'N', 'endpoint' => 'https://node.example', 'status' => 'ONLINE']);
        $deployment = Deployment::create(['uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'node_id' => $node->id, 'hostname' => 'private.cloud.centralcorp.fr', 'state' => 'active', 'desired_state' => 'active', 'memory_bytes' => 1, 'cpu_limit' => .5, 'image_reference' => 'official-image']);
        Http::fake();

        $this->actingAs($a)->get(route('deployments.logs.data', $deployment->uuid))->assertForbidden();
        Http::assertNothingSent();
    }

    private function fixture(): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $plan = Plan::create(['uuid' => (string) Str::uuid(), 'name' => 'Standard', 'slug' => 'standard', 'active' => true, 'price' => 1000, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 536870912, 'cpu_limit' => .5]);
        $project = Project::create(['uuid' => (string) Str::uuid(), 'owner_id' => $b->id, 'plan_id' => $plan->id, 'name' => 'Secret Project', 'status' => 'ACTIVE']);

        return [$a, $b, $project];
    }
}
