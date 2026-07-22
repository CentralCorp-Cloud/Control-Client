<?php

namespace App\Services;

use App\Contracts\CnameResolver;

final class NativeCnameResolver implements CnameResolver
{
    public function resolve(string $hostname): ?string
    {
        $records = @dns_get_record($hostname, DNS_CNAME);
        if (! is_array($records)) {
            return null;
        }

        foreach ($records as $record) {
            if (isset($record['target']) && is_string($record['target'])) {
                return strtolower(rtrim($record['target'], '.'));
            }
        }

        return null;
    }
}
