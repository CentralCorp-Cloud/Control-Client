<?php

namespace App\Contracts;

use App\Models\NodeEnrollment;
use App\ValueObjects\IssuedNodeCertificate;

interface NodeCertificateIssuer
{
    public function issue(NodeEnrollment $enrollment, string $csr): IssuedNodeCertificate;
}
