<?php

namespace App\Services;

use Illuminate\Support\Str;

final class DomainNameService
{
    public static function normalizeLabel(?string $value): ?string
    {
        $label = strtolower(trim((string) $value));

        return $label === '' ? null : $label;
    }

    public static function normalizeCustomInput(?string $value): ?string
    {
        $input = trim((string) $value);
        if ($input === '') {
            return null;
        }

        $url = str_contains($input, '://') ? $input : 'https://'.$input;
        $parts = parse_url($url);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['port'], $parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }

        return strtolower(rtrim((string) $parts['host'], '.'));
    }

    public static function isValidLabel(?string $label): bool
    {
        return is_string($label)
            && strlen($label) <= 63
            && preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label) === 1
            && ! in_array($label, config('centralcloud.panel.reserved_subdomains', []), true);
    }

    public static function isValidHostname(?string $hostname): bool
    {
        if (! is_string($hostname) || strlen($hostname) > 253 || filter_var($hostname, FILTER_VALIDATE_IP) !== false || str_contains($hostname, '*')) {
            return false;
        }

        $labels = explode('.', $hostname);

        return count($labels) >= 2 && collect($labels)->every(fn (string $label) => self::isDnsLabel($label));
    }

    public static function centralHostname(string $label): string
    {
        return $label.'.'.self::suffix();
    }

    public static function opaqueCentralHostname(string $uuid): string
    {
        return self::centralHostname('panel-'.substr(str_replace('-', '', Str::lower($uuid)), 0, 20));
    }

    public static function suffix(): string
    {
        return strtolower(trim((string) config('centralcloud.panel.domain_suffix'), '.'));
    }

    private static function isDnsLabel(string $label): bool
    {
        return strlen($label) >= 1
            && strlen($label) <= 63
            && preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label) === 1;
    }
}
