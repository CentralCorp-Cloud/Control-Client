<?php

namespace App\Console\Commands;

use App\Enums\NodeEnrollmentStatus;
use App\Models\NodeEnrollment;
use Illuminate\Console\Command;

class CleanupNodeEnrollments extends Command
{
    protected $signature = 'centralcloud:enrollments:cleanup';

    protected $description = 'Expire stale node enrollments and remove old public certificate material';

    public function handle(): int
    {
        NodeEnrollment::where('expires_at', '<=', now())
            ->whereIn('status', [NodeEnrollmentStatus::PendingClaim, NodeEnrollmentStatus::AwaitingApproval])
            ->update(['status' => NodeEnrollmentStatus::Expired, 'device_code_hash' => null, 'user_code_hash' => null, 'preauthorization_token_hash' => null, 'last_activity_at' => now()]);

        NodeEnrollment::where('updated_at', '<', now()->subDays(30))
            ->whereIn('status', [NodeEnrollmentStatus::Ready, NodeEnrollmentStatus::Denied, NodeEnrollmentStatus::Expired, NodeEnrollmentStatus::Revoked])
            ->update(['issued_certificate' => null, 'issued_chain' => null, 'completion_report' => null]);

        NodeEnrollment::whereNotNull('bootstrap_token_hash')
            ->where('bootstrap_expires_at', '<=', now())
            ->update(['bootstrap_token_hash' => null]);

        return self::SUCCESS;
    }
}
