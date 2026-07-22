<?php

namespace App\Services\Billing;

use App\Enums\ProjectStatus;
use App\Models\BillingTransaction;
use App\Models\Project;
use App\Models\StripeEvent;
use App\Models\Subscription;
use App\Notifications\PaymentFailedNotification;
use App\Services\DeploymentProvisioningService;
use App\Services\DomainVerificationService;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StripeEventProcessor
{
    public function __construct(private DeploymentProvisioningService $provisioning, private DomainVerificationService $domains) {}

    public function process(StripeEvent $event): void
    {
        if ($event->status === 'PROCESSED') {
            return;
        } try {
            $payload = $event->payload;
            $object = $payload['data']['object'] ?? [];
            match ($event->type) {
                'checkout.session.completed' => $this->checkout($object),'customer.subscription.created','customer.subscription.updated' => $this->subscription($object),'customer.subscription.deleted' => $this->deleted($object),'invoice.paid','invoice.payment_succeeded' => $this->invoicePaid($object),'invoice.payment_failed' => $this->invoiceFailed($object),default => null
            };
            $event->update(['status' => 'PROCESSED', 'processed_at' => now(), 'last_error' => null]);
        } catch (Throwable $e) {
            $event->update(['status' => 'FAILED', 'last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            report($e);
        }
    }

    private function checkout(array $o): void
    {
        if (! in_array($o['payment_status'] ?? null, ['paid', 'no_payment_required'], true)) {
            return;
        }$project = Project::where('uuid', $o['metadata']['project_uuid'] ?? null)->first();
        if (! $project) {
            return;
        }DB::transaction(fn () => $project->update(['status' => $project->isCustomDomain() && ! $project->domain_verified_at ? ProjectStatus::PendingDomain : ProjectStatus::PaymentConfirmed, 'payment_confirmed_at' => now()]));
        try {
            $project = $project->fresh();
            if ($project->isCustomDomain() && ! $project->domain_verified_at) {
                $this->domains->verify($project);
            } else {
                $this->provisioning->provision($project);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function subscription(array $o): void
    {
        $project = Project::where('uuid', $o['metadata']['project_uuid'] ?? null)->first();
        if (! $project) {
            return;
        }Subscription::updateOrCreate(['stripe_id' => $o['id']], ['user_id' => $project->owner_id, 'project_id' => $project->id, 'plan_id' => $project->plan_id, 'type' => 'project:'.$project->uuid, 'stripe_status' => $o['status'], 'stripe_price' => $o['items']['data'][0]['price']['id'] ?? null, 'quantity' => $o['items']['data'][0]['quantity'] ?? 1, 'trial_ends_at' => isset($o['trial_end']) ? now()->setTimestamp($o['trial_end']) : null, 'ends_at' => isset($o['cancel_at']) ? now()->setTimestamp($o['cancel_at']) : null]);
        if ($o['status'] === 'past_due') {
            $this->markPastDue($project);
        }
    }

    private function deleted(array $o): void
    {
        $subscription = Subscription::where('stripe_id', $o['id'])->first();
        $subscription?->project?->update(['status' => ProjectStatus::Cancelled, 'cancelled_at' => now()]);
    }

    private function invoicePaid(array $o): void
    {
        $project = $this->projectFromInvoice($o);
        $this->recordInvoice($o, $project, 'paid');
        if ($project?->status === ProjectStatus::PaymentPastDue) {
            $project->update(['status' => ProjectStatus::Active]);
        }
    }

    private function invoiceFailed(array $o): void
    {
        if ($project = $this->projectFromInvoice($o)) {
            $this->recordInvoice($o, $project, 'failed');
            $this->markPastDue($project);
        }
    }

    private function recordInvoice(array $invoice, ?Project $project, string $status): void
    {
        if (! $project || empty($invoice['id'])) {
            return;
        }
        $paymentIntent = $invoice['payment_intent'] ?? null;
        BillingTransaction::updateOrCreate(
            ['stripe_invoice_id' => $invoice['id']],
            [
                'user_id' => $project->owner_id,
                'project_id' => $project->id,
                'stripe_payment_intent_id' => is_string($paymentIntent) ? $paymentIntent : ($paymentIntent['id'] ?? null),
                'status' => $status,
                'amount' => (int) ($status === 'paid' ? ($invoice['amount_paid'] ?? 0) : ($invoice['amount_due'] ?? 0)),
                'currency' => strtoupper((string) ($invoice['currency'] ?? 'EUR')),
                'paid_at' => $status === 'paid' ? now() : null,
            ]
        );
    }

    private function projectFromInvoice(array $o): ?Project
    {
        $stripeId = $o['parent']['subscription_details']['subscription'] ?? $o['subscription'] ?? null;

        return Subscription::where('stripe_id', $stripeId)->first()?->project;
    }

    private function markPastDue(Project $project): void
    {
        $project->update(['status' => ProjectStatus::PaymentPastDue]);
        $project->owner->notify(new PaymentFailedNotification($project));
    }
}
