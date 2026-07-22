<?php

namespace App\Console\Commands;

use App\Enums\DomainMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\DomainVerificationService;
use Illuminate\Console\Command;
use Throwable;

class VerifyProjectDomains extends Command
{
    protected $signature = 'centralcloud:domains:verify {--limit=25}';

    protected $description = 'Verify pending custom-domain CNAME records';

    public function handle(DomainVerificationService $verification): int
    {
        Project::query()
            ->where('domain_mode', DomainMode::Custom->value)
            ->where('status', ProjectStatus::PendingDomain->value)
            ->whereNull('domain_verified_at')
            ->whereNotNull('payment_confirmed_at')
            ->orderBy('domain_last_checked_at')
            ->limit((int) $this->option('limit'))
            ->each(function (Project $project) use ($verification): void {
                try {
                    $verification->verify($project);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        return self::SUCCESS;
    }
}
