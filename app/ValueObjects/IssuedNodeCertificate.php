<?php

namespace App\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class IssuedNodeCertificate
{
    public function __construct(
        public string $certificate,
        public string $chain,
        public string $clientCa,
        public string $serial,
        public CarbonImmutable $expiresAt,
    ) {}
}
