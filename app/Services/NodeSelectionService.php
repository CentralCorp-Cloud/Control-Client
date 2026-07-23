<?php

namespace App\Services;

use App\Enums\NodeStatus;
use App\Models\Node;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Support\Collection;

final class NodeSelectionService
{
    public function select(Plan|Project $subject): ?Node
    {
        return $this->eligible($subject)->sortByDesc(fn (Node $n) => $this->score($n))->first();
    }

    public function eligible(Plan|Project $subject): Collection
    {
        $plan = $subject instanceof Project ? $subject->plan : $subject;
        $requiresAliases = $subject instanceof Project && $subject->isCustomDomain();
        $ram = (int) Setting::valueFor('scheduler_ram_margin', config('centralcloud.nodes.ram_margin_bytes'));
        $disk = (int) Setting::valueFor('scheduler_disk_margin', config('centralcloud.nodes.disk_margin_bytes'));
        $max = (int) config('centralcloud.nodes.maximum_deployments');

        return Node::query()->whereIn('status', [NodeStatus::Online->value, NodeStatus::Ready->value])->where('scheduling_enabled', true)->where('maintenance', false)->where('last_seen_at', '>=', now()->subMinutes(config('centralcloud.nodes.offline_after_minutes')))->where('memory_available_bytes', '>=', $plan->memory_bytes + $ram)->where('disk_available_bytes', '>=', $disk)->where('deployment_count', '<', $max)->get()->filter(fn (Node $node) => ! $requiresAliases || $node->supports('hostname_aliases'));
    }

    public function score(Node $n): float
    {
        $ram = $n->memory_total_bytes ? ($n->memory_available_bytes / $n->memory_total_bytes) : 0;
        $disk = $n->disk_total_bytes ? ($n->disk_available_bytes / $n->disk_total_bytes) : 0;
        $max = max(1, (int) config('centralcloud.nodes.maximum_deployments'));
        $slots = max(0, ($max - $n->deployment_count) / $max);

        return .5 * $ram + .3 * $disk + .2 * $slots;
    }
}
