<?php

namespace App\Enums;

enum NodeEnrollmentStep: string
{
    case Preflight = 'preflight';
    case WaitingForClaim = 'waiting_for_claim';
    case Packages = 'packages';
    case Docker = 'docker';
    case PostgreSQL = 'postgresql';
    case Traefik = 'traefik';
    case TLS = 'tls';
    case Agent = 'agent';
    case Firewall = 'firewall';
    case Validation = 'validation';
    case Complete = 'complete';

    public function order(): int
    {
        return array_search($this, self::cases(), true);
    }
}
