<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentOperation;
use App\Models\Deployment;
use App\Models\Incident;
use App\Models\Node;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $stats = [
            'users' => ['total' => User::count(), 'active' => User::where('status', 'ACTIVE')->count(), 'suspended' => User::where('status', 'SUSPENDED')->count(), 'new_24h' => User::where('created_at', '>=', now()->subDay())->count(), 'new_7d' => User::where('created_at', '>=', now()->subDays(7))->count(), 'new_30d' => User::where('created_at', '>=', now()->subDays(30))->count()],
            'projects' => ['total' => Project::count(), 'active' => Project::where('status', 'ACTIVE')->count(), 'stopped' => Deployment::where('state', 'stopped')->count(), 'failed' => Project::where('status', 'PROVISIONING_FAILED')->count(), 'provisioning' => Project::whereIn('status', ['PENDING_DOMAIN', 'PAYMENT_CONFIRMED', 'PENDING_CAPACITY', 'PROVISIONING'])->count(), 'suspended' => Project::where('status', 'SUSPENDED')->count()],
            'nodes' => ['online' => Node::where('status', 'ONLINE')->count(), 'degraded' => Node::where('status', 'DEGRADED')->count(), 'offline' => Node::where('status', 'OFFLINE')->count(), 'maintenance' => Node::where('status', 'MAINTENANCE')->count()],
            'billing' => ['active' => Subscription::whereIn('stripe_status', ['active', 'trialing'])->count(), 'past_due' => Subscription::where('stripe_status', 'past_due')->count(), 'cancelled' => Subscription::whereIn('stripe_status', ['canceled', 'cancelled'])->count()],
            'operations' => ['running' => AgentOperation::whereIn('status', ['QUEUED', 'RUNNING'])->count(), 'failed_24h' => AgentOperation::where('status', 'FAILED')->where('updated_at', '>=', now()->subDay())->count()],
            'infrastructure' => ['ram_total' => Node::sum('memory_total_bytes'), 'ram_available' => Node::sum('memory_available_bytes'), 'disk_available' => Node::sum('disk_available_bytes')],
            'incidents' => Incident::where('status', 'OPEN')->count(),
        ];
        $nodes = Node::orderBy('name')->get();
        $incidents = Incident::where('status', 'OPEN')->latest('last_seen_at')->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'nodes', 'incidents'));
    }
}
