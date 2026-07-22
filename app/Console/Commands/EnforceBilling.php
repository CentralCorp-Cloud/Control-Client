<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Setting;
use App\Services\DeploymentLifecycleService;
use Illuminate\Console\Command;

class EnforceBilling extends Command
{
    protected $signature = 'centralcloud:billing:enforce';

    protected $description = 'Apply the payment grace policy without purging data';

    public function handle(DeploymentLifecycleService $lifecycle): int
    {
        $graceDays = max(1, (int) Setting::valueFor('payment_grace_days', config('centralcloud.billing.suspension_grace_days')));
        Project::with('deployment')->where('status', ProjectStatus::PaymentPastDue)->where('updated_at', '<=', now()->subDays($graceDays))->each(function (Project $p) use ($lifecycle) {
            if ($p->deployment && ! $p->deployment->hasActiveOperation()) {
                $lifecycle->softDelete($p->deployment);
                $p->update(['status' => ProjectStatus::Suspended, 'suspended_at' => now()]);
            }
        });

        return self::SUCCESS;
    }
}
