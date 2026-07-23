<?php

namespace App\Services\Enrollment;

use App\Contracts\EnrollmentRandomSource;

final class SecureRandomSource implements EnrollmentRandomSource
{
    public function bytes(int $length): string
    {
        return random_bytes($length);
    }
}
