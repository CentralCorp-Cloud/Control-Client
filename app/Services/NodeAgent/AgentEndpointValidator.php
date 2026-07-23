<?php

namespace App\Services\NodeAgent;

use Illuminate\Validation\ValidationException;

final class AgentEndpointValidator
{
    public function validate(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $parts['host'] ?? null;
        if (! $host || ($scheme !== 'https' && app()->environment('production'))) {
            throw ValidationException::withMessages(['endpoint' => 'Une URL HTTPS Agent valide est requise.']);
        }
        if (! in_array((int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)), config('centralcloud.agent.allowed_ports'), true)) {
            throw ValidationException::withMessages(['endpoint' => 'Le port de cet endpoint Agent n’est pas autorisé.']);
        }
        $cidrs = config('centralcloud.agent.allowed_cidrs', []);
        if (! $cidrs) {
            return;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_values(array_unique(array_filter(array_merge(gethostbynamel($host) ?: [], $this->ipv6($host)))));
        if (! $ips || collect($ips)->contains(fn (string $ip) => ! $this->inAnyCidr($ip, $cidrs))) {
            throw ValidationException::withMessages(['endpoint' => 'La résolution de cet endpoint sort de l’allowlist réseau Agent.']);
        }
    }

    private function ipv6(string $host): array
    {
        $records = dns_get_record($host, DNS_AAAA);

        return array_column($records ?: [], 'ipv6');
    }

    private function inAnyCidr(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            [$network,$bits] = array_pad(explode('/', $cidr, 2), 2, null);
            if ($this->contains($ip, $network, (int) ($bits ?? (str_contains($network, ':') ? 128 : 32)))) {
                return true;
            }
        }

        return false;
    }

    private function contains(string $ip, string $network, int $bits): bool
    {
        $a = inet_pton($ip);
        $n = inet_pton($network);
        if ($a === false || $n === false || strlen($a) !== strlen($n) || $bits < 0 || $bits > strlen($a) * 8) {
            return false;
        } $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if (substr($a, 0, $bytes) !== substr($n, 0, $bytes)) {
            return false;
        }

        return $rem === 0 || ((ord($a[$bytes]) & (0xFF << (8 - $rem))) === (ord($n[$bytes]) & (0xFF << (8 - $rem))));
    }
}
