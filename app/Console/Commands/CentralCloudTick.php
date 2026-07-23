<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CentralCloudTick extends Command
{
    protected $signature = 'centralcloud:tick';

    protected $description = 'Run one bounded shared-hosting control-plane cycle';

    public function handle(): int
    {
        $lock = Cache::lock('centralcloud:tick', 300);
        if (! $lock->get()) {
            return self::SUCCESS;
        } try {
            foreach (['centralcloud:enrollments:cleanup', 'centralcloud:enrollments:validate', 'centralcloud:requests:retry', 'centralcloud:nodes:poll', 'centralcloud:operations:poll', 'centralcloud:deployments:sync', 'centralcloud:domains:verify', 'centralcloud:billing:reconcile', 'centralcloud:billing:enforce', 'centralcloud:incidents:refresh'] as $command) {
                Artisan::call($command);
            } Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 30, '--tries' => 3]);
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
