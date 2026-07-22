<?php

namespace Database\Seeders;

use App\Models\Deployment;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plans = collect([['Starter', 990, 268435456, .25], ['Standard', 1990, 536870912, .5], ['Pro', 3990, 1073741824, 1]])->map(fn ($p) => Plan::updateOrCreate(['slug' => Str::slug($p[0])], ['uuid' => (string) Str::uuid(), 'name' => $p[0], 'description' => 'Hébergement CentralPanel managé', 'active' => true, 'price' => $p[1], 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => $p[2], 'cpu_limit' => $p[3], 'sort_order' => $p[1]]));
        Plan::updateOrCreate(['slug' => 'decouverte'], ['uuid' => (string) Str::uuid(), 'name' => 'Découverte', 'description' => 'Un CentralPanel gratuit pour découvrir CentralCloud', 'active' => true, 'is_free' => true, 'price' => 0, 'currency' => 'EUR', 'billing_interval' => 'month', 'memory_bytes' => 268435456, 'cpu_limit' => .25, 'maximum_projects' => 1, 'sort_order' => 0]);
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $password = env('CENTRALCLOUD_DEV_ADMIN_PASSWORD');
        $email = env('CENTRALCLOUD_DEV_ADMIN_EMAIL');
        if ($email && $password) {
            User::updateOrCreate(['email' => $email], ['uuid' => (string) Str::uuid(), 'name' => 'CentralCloud Admin', 'password' => Hash::make($password), 'role' => 'SUPER_ADMIN', 'status' => 'ACTIVE', 'email_verified_at' => now()]);
        }
        $users = User::factory(5)->create();
        $node = Node::create(['uuid' => (string) Str::uuid(), 'agent_node_id' => (string) Str::uuid(), 'name' => 'Paris Mock 01', 'endpoint' => 'https://127.0.0.1:9443', 'region' => 'Paris', 'status' => 'ONLINE', 'scheduling_enabled' => false, 'agent_version' => 'mock', 'capabilities' => ['hostname_aliases'], 'cpu_count' => 8, 'memory_total_bytes' => 17179869184, 'memory_available_bytes' => 8589934592, 'disk_total_bytes' => 107374182400, 'disk_available_bytes' => 53687091200, 'last_seen_at' => now()]);
        $users->each(function ($user) use ($plans, $node) {
            $hostname = Str::lower(Str::random(20)).'.'.config('centralcloud.panel.domain_suffix');
            $project = Project::create(['uuid' => (string) Str::uuid(), 'owner_id' => $user->id, 'plan_id' => $plans[1]->id, 'name' => 'Launcher '.fake()->word(), 'status' => 'ACTIVE', 'domain_mode' => 'CENTRALCLOUD', 'canonical_hostname' => $hostname, 'payment_confirmed_at' => now()]);
            Deployment::create(['uuid' => (string) Str::uuid(), 'project_id' => $project->id, 'node_id' => $node->id, 'hostname' => $hostname, 'state' => 'active', 'desired_state' => 'active', 'memory_bytes' => $plans[1]->memory_bytes, 'cpu_limit' => $plans[1]->cpu_limit, 'image_reference' => 'ghcr.io/centralcorp/centralpanel@sha256:'.str_repeat('a', 64), 'deployed_at' => now(), 'last_synced_at' => now()]);
        });
    }
}
