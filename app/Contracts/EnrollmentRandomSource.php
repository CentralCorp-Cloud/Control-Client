<?php

namespace App\Contracts;

interface EnrollmentRandomSource
{
    public function bytes(int $length): string;
}
