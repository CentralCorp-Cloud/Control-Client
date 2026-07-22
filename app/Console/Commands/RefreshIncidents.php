<?php

namespace App\Console\Commands;

use App\Enums\NodeStatus;
use App\Models\Incident;
use App\Models\Node;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RefreshIncidents extends Command
{
    protected $signature = 'centralcloud:incidents:refresh';

    protected $description = 'Refresh computed operational incidents';

    public function handle(): int
    {
        Node::where('last_seen_at', '<', now()->subMinutes(config('centralcloud.nodes.offline_after_minutes')))->each(function (Node $n) {
            $n->update(['status' => NodeStatus::Offline]);
            Incident::updateOrCreate(['fingerprint' => "node:{$n->id}:stale"], ['uuid' => (string) Str::uuid(), 'severity' => 'CRITICAL', 'source_type' => 'node', 'source_id' => (string) $n->id, 'message' => "Aucun heartbeat récent pour {$n->name}.", 'status' => 'OPEN', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        });

        return self::SUCCESS;
    }
}
