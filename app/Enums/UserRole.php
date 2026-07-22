<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'USER';
    case Support = 'SUPPORT';
    case BillingAdmin = 'BILLING_ADMIN';
    case InfraAdmin = 'INFRA_ADMIN';
    case Admin = 'ADMIN';
    case SuperAdmin = 'SUPER_ADMIN';

    public function isAdministrator(): bool
    {
        return $this !== self::User;
    }
}
