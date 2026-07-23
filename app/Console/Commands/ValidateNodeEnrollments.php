<?php

namespace App\Console\Commands;

use App\Enums\NodeEnrollmentStatus;
use App\Models\NodeEnrollment;
use App\Services\Enrollment\NodeEnrollmentService;
use Illuminate\Console\Command;

class ValidateNodeEnrollments extends Command
{
    protected $signature = 'centralcloud:enrollments:validate {--limit=10}';

    protected $description = 'Retry bounded HTTPS validation of completed node installations';

    public function handle(NodeEnrollmentService $service): int
    {
        NodeEnrollment::with('node')->where('status', NodeEnrollmentStatus::Validating)
            ->oldest('last_activity_at')->limit(min(50, max(1, (int) $this->option('limit'))))
            ->get()->each(fn (NodeEnrollment $enrollment) => $service->validateAgent($enrollment));

        return self::SUCCESS;
    }
}
