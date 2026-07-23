<?php

namespace App\Enums;

enum NodeEnrollmentStatus: string
{
    case PendingClaim = 'pending_claim';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Installing = 'installing';
    case Validating = 'validating';
    case Ready = 'ready';
    case Denied = 'denied';
    case Expired = 'expired';
    case Failed = 'failed';
    case Revoked = 'revoked';

    public function terminal(): bool
    {
        return in_array($this, [self::Ready, self::Denied, self::Expired, self::Failed, self::Revoked], true);
    }
}
