<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\StripeEvent;
use App\Services\Billing\StripeEventProcessor;
use App\Services\DeploymentProvisioningService;
use Illuminate\Console\Command;

class ReconcileBilling extends Command
{
    protected $signature = 'centralcloud:billing:reconcile';

    protected $description = 'Reconcile pending Stripe events';

    public function handle(): int
    {
        StripeEvent::whereIn('status', ['RECEIVED', 'FAILED'])->orderBy('id')->limit(100)->each(fn ($event) => app(StripeEventProcessor::class)->process($event));
        Project::with(['plan', 'owner', 'provisioningRequest'])->whereIn('status', ['PAYMENT_CONFIRMED', 'PENDING_CAPACITY'])->whereHas('provisioningRequest', fn ($query) => $query->whereNull('consumed_at'))->limit(25)->each(function ($project): void {
            try {
                app(DeploymentProvisioningService::class)->provision($project);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
