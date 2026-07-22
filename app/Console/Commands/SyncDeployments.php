<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use App\Services\NodeAgent\NodeAgentClient;
use Illuminate\Console\Command;
use Throwable;

class SyncDeployments extends Command
{
    protected $signature = 'centralcloud:deployments:sync {--limit=100}';

    protected $description = 'Synchronize technical deployment state';

    public function handle(NodeAgentClient $client): int
    {
        Deployment::with('node')->whereNotNull('node_id')->orderBy('last_synced_at')->limit((int) $this->option('limit'))->each(function (Deployment $d) use ($client) {
            if (! $d->node) {
                return;
            }
            try {
                $data = $client->deployment($d->node, $d->uuid);
                $d->update(['state' => $data['state'], 'image_reference' => $data['image'] ?? $d->image_reference, 'memory_bytes' => $data['resources']['memory_bytes'] ?? $d->memory_bytes, 'cpu_limit' => $data['resources']['cpu_limit'] ?? $d->cpu_limit, 'last_synced_at' => now()]);
            } catch (Throwable) {
            }
        });

        return self::SUCCESS;
    }
}
