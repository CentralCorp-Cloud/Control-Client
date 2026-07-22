<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NodeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNodeRequest;
use App\Models\Node;
use App\Services\AuditService;
use App\Services\NodeAgent\AgentEndpointValidator;
use App\Services\NodeAgent\NodeAgentClient;
use App\Services\NodeHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NodeController extends Controller
{
    public function index()
    {
        $nodes = Node::latest()->paginate(25);

        return view('admin.nodes.index', compact('nodes'));
    }

    public function create()
    {
        return view('admin.nodes.create');
    }

    public function store(StoreNodeRequest $request, AgentEndpointValidator $validator, NodeAgentClient $client, AuditService $audit)
    {
        $validator->validate($request->endpoint);
        $probe = new Node(['endpoint' => rtrim($request->endpoint, '/')]);
        $health = $client->health($probe);
        $resources = $client->resources($probe);
        abort_if(($health['node_id'] ?? null) !== ($resources['node_id'] ?? null), 422, 'Identité Agent incohérente.');
        abort_if(Node::where('agent_node_id', $health['node_id'] ?? null)->exists(), 422, 'Ce Node Agent est déjà enregistré.');
        $capabilities = is_array($health['capabilities'] ?? null) ? array_values(array_filter($health['capabilities'], 'is_string')) : [];
        $node = Node::create(['uuid' => (string) Str::uuid(), 'agent_node_id' => $health['node_id'], 'name' => $request->name, 'endpoint' => rtrim($request->endpoint, '/'), 'region' => $request->region, 'status' => ($health['status'] ?? null) === 'ok' ? NodeStatus::Online : NodeStatus::Degraded, 'scheduling_enabled' => false, 'agent_version' => $health['agent_version'] ?? $health['version'] ?? null, 'capabilities' => $capabilities, 'cpu_count' => $resources['cpu_count'], 'memory_total_bytes' => $resources['memory_total_bytes'], 'memory_available_bytes' => $resources['memory_available_bytes'], 'disk_total_bytes' => $resources['disk_total_bytes'], 'disk_available_bytes' => $resources['disk_available_bytes'], 'deployment_count' => $resources['deployment_count'], 'active_deployment_count' => $resources['active_deployment_count'], 'last_health_status' => $health['status'], 'last_seen_at' => now()]);
        $audit->record('node.created', $node, ['agent_node_id' => $node->agent_node_id]);

        return redirect()->route('admin.nodes.show', $node)->with('success', 'Node ajouté.');
    }

    public function show(Node $node)
    {
        $node->load(['deployments.project', 'operations' => fn ($q) => $q->latest()->limit(20)]);

        return view('admin.nodes.show', compact('node'));
    }

    public function update(Request $request, Node $node, NodeHealthService $health, AuditService $audit)
    {
        if ($request->has('scheduling_enabled')) {
            $node->update(['scheduling_enabled' => $request->boolean('scheduling_enabled')]);
            $audit->record('node.scheduling_changed', $node, ['enabled' => $node->scheduling_enabled]);
        }if ($request->has('maintenance')) {
            $enabled = $request->boolean('maintenance');
            $node->update(['maintenance' => $enabled, 'status' => $enabled ? NodeStatus::Maintenance : NodeStatus::Offline, 'scheduling_enabled' => false]);
            $audit->record($enabled ? 'node.maintenance_enabled' : 'node.maintenance_disabled', $node);
        }if ($request->boolean('refresh')) {
            $health->poll($node);
        }

        return back()->with('success', 'Node mis à jour.');
    }

    public function destroy(Node $node, AuditService $audit)
    {
        abort_if($node->deployments()->exists(), 409, 'Ce Node héberge encore des deployments.');
        $audit->record('node.deleted', $node);
        $node->delete();

        return redirect()->route('admin.nodes.index');
    }
}
