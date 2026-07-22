<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case PendingDomain = 'PENDING_DOMAIN';
    case PaymentConfirmed = 'PAYMENT_CONFIRMED';
    case PendingCapacity = 'PENDING_CAPACITY';
    case Provisioning = 'PROVISIONING';
    case Active = 'ACTIVE';
    case PaymentPastDue = 'PAYMENT_PAST_DUE';
    case Suspended = 'SUSPENDED';
    case Cancelled = 'CANCELLED';
    case PendingDeletion = 'PENDING_DELETION';
    case ProvisioningFailed = 'PROVISIONING_FAILED';
}
