<?php

namespace App\Contracts;

interface CnameResolver
{
    public function resolve(string $hostname): ?string;
}
