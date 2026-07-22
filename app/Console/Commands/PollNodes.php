<?php

namespace App\Console\Commands;

use App\Models\Node;
use App\Services\NodeHealthService;
use Illuminate\Console\Command;

class PollNodes extends Command
{
    protected $signature = 'centralcloud:nodes:poll {--limit=50}';

    protected $description = 'Poll Node Agent health and resources';

    public function handle(NodeHealthService $service): int
    {
        Node::query()->limit((int) $this->option('limit'))->each(fn (Node $node) => $service->poll($node));

        return self::SUCCESS;
    }
}
