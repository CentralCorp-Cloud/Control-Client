<?php

namespace App\Console\Commands;

use App\Enums\NodeEnrollmentStatus;
use App\Enums\NodeStatus;
use App\Models\NodeEnrollment;
use Illuminate\Console\Command;

class CleanupNodeEnrollments extends Command
{
    protected $signature = 'centralcloud:enrollments:cleanup';

    protected $description = 'Expire stale node enrollments and remove obsolete enrollment material';

    public function handle(): int
    {
        NodeEnrollment::where('expires_at', '<=', now())
            ->whereIn('status', [NodeEnrollmentStatus::PendingClaim, NodeEnrollmentStatus::AwaitingApproval])
            ->update(['status' => NodeEnrollmentStatus::Expired, 'device_code_hash' => null, 'user_code_hash' => null, 'preauthorization_token_hash' => null, 'last_activity_at' => now()]);

        NodeEnrollment::with('node')
            ->where('expires_at', '<=', now())
            ->where('status', NodeEnrollmentStatus::Approved)
            ->whereNull('bootstrap_token_delivered_at')
            ->each(function (NodeEnrollment $enrollment): void {
                $enrollment->update([
                    'status' => NodeEnrollmentStatus::Expired,
                    'device_code_hash' => null,
                    'user_code_hash' => null,
                    'preauthorization_token_hash' => null,
                    'last_activity_at' => now(),
                ]);
                $enrollment->node?->update([
                    'agent_token' => null,
                    'status' => NodeStatus::Offline,
                    'scheduling_enabled' => false,
                ]);
            });

        NodeEnrollment::where('updated_at', '<', now()->subDays(30))
            ->whereIn('status', [NodeEnrollmentStatus::Ready, NodeEnrollmentStatus::Denied, NodeEnrollmentStatus::Expired, NodeEnrollmentStatus::Revoked])
            ->update(['issued_certificate' => null, 'issued_chain' => null, 'completion_report' => null]);

        NodeEnrollment::whereNotNull('bootstrap_token_hash')
            ->where('bootstrap_expires_at', '<=', now())
            ->update(['bootstrap_token_hash' => null]);

        return self::SUCCESS;
    }
}
