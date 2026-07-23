<?php

namespace App\Services\Certificates;

use App\Contracts\NodeCertificateIssuer;
use App\Models\NodeEnrollment;
use App\ValueObjects\IssuedNodeCertificate;
use Carbon\CarbonImmutable;
use phpseclib3\File\X509;
use RuntimeException;

final class LocalCaNodeCertificateIssuer implements NodeCertificateIssuer
{
    public function issue(NodeEnrollment $enrollment, string $csr): IssuedNodeCertificate
    {
        $caCertPath = (string) config('centralcloud.enrollment.ca_cert_path');
        $caKeyPath = (string) config('centralcloud.enrollment.ca_key_path');
        $clientCaPath = (string) config('centralcloud.enrollment.control_plane_client_ca_path');
        $this->assertProtectedFile($caKeyPath, true);
        $this->assertProtectedFile($caCertPath, false);
        $this->assertProtectedFile($clientCaPath, false);

        $caCert = $this->read($caCertPath);
        $caKey = $this->read($caKeyPath);
        $clientCa = $this->read($clientCaPath);
        $this->assertCertificateAuthority($caCert, $caKey);
        $this->assertCertificateAuthority($clientCa);
        $publicKey = @openssl_csr_get_public_key($csr);
        if ($publicKey === false) {
            throw new RuntimeException('invalid_csr');
        }
        $details = openssl_pkey_get_details($publicKey);
        if (! is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($details['bits'] ?? 0) < 256) {
            throw new RuntimeException('invalid_csr_key');
        }

        $expected = ['DNS:'.$enrollment->agent_fqdn, 'URI:spiffe://centralcloud/node/'.$enrollment->node->agent_node_id];
        $actual = $this->csrSans($csr);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new RuntimeException('invalid_csr_san');
        }

        $days = max(1, (int) config('centralcloud.enrollment.certificate_days', 90));
        $serial = random_int(1, PHP_INT_MAX);
        $opensslConfig = $this->certificateConfig($enrollment);
        try {
            $signed = @openssl_csr_sign($csr, $caCert, $caKey, $days, [
                'config' => $opensslConfig,
                'digest_alg' => 'sha256',
                'x509_extensions' => 'centralcloud_node',
            ], $serial);
        } finally {
            @unlink($opensslConfig);
        }
        if ($signed === false || ! openssl_x509_export($signed, $certificate)) {
            throw new RuntimeException('certificate_signing_failed');
        }
        $parsed = openssl_x509_parse($certificate, false);
        $actual = $this->parseSans((string) data_get($parsed, 'extensions.subjectAltName', ''));
        sort($actual);
        if ($actual !== $expected) {
            throw new RuntimeException('certificate_signing_failed');
        }
        $expiresAt = CarbonImmutable::createFromTimestampUTC((int) ($parsed['validTo_time_t'] ?? 0));
        if ($expiresAt->isPast()) {
            throw new RuntimeException('certificate_signing_failed');
        }

        return new IssuedNodeCertificate($certificate, $caCert, $clientCa, dechex($serial), $expiresAt);
    }

    private function assertCertificateAuthority(string $certificate, ?string $privateKey = null): void
    {
        $parsed = @openssl_x509_parse($certificate, false);
        if (! is_array($parsed)
            || ! str_contains((string) data_get($parsed, 'extensions.basicConstraints', ''), 'CA:TRUE')
            || (int) ($parsed['validTo_time_t'] ?? 0) <= time()) {
            throw new RuntimeException('certificate_issuer_not_configured');
        }
        if ($privateKey !== null && ! @openssl_x509_check_private_key($certificate, $privateKey)) {
            throw new RuntimeException('certificate_issuer_not_configured');
        }
    }

    /** @return list<string> */
    private function csrSans(string $csr): array
    {
        $x509 = new X509;
        if ($x509->loadCSR($csr) === false || $x509->validateSignature(false) !== true) {
            throw new RuntimeException('invalid_csr');
        }

        $extension = $x509->getExtension('id-ce-subjectAltName');
        if (! is_array($extension)) {
            return [];
        }

        $sans = [];
        foreach ($extension as $name) {
            if (isset($name['dNSName']) && is_string($name['dNSName'])) {
                $sans[] = 'DNS:'.$name['dNSName'];
            } elseif (isset($name['uniformResourceIdentifier']) && is_string($name['uniformResourceIdentifier'])) {
                $sans[] = 'URI:'.$name['uniformResourceIdentifier'];
            } else {
                throw new RuntimeException('invalid_csr_san');
            }
        }

        return $sans;
    }

    private function certificateConfig(NodeEnrollment $enrollment): string
    {
        $fqdn = (string) $enrollment->agent_fqdn;
        $nodeId = (string) $enrollment->node->agent_node_id;
        if (preg_match('/\A(?=.{1,253}\z)[A-Za-z0-9.-]+\z/', $fqdn) !== 1
            || preg_match('/\A[0-9a-f-]{36}\z/i', $nodeId) !== 1) {
            throw new RuntimeException('invalid_csr_san');
        }

        $path = tempnam(sys_get_temp_dir(), 'centralcloud-x509-');
        if ($path === false || ! chmod($path, 0600)) {
            throw new RuntimeException('certificate_signing_failed');
        }
        $contents = <<<CONF
            [ centralcloud_node ]
            basicConstraints = critical, CA:FALSE
            keyUsage = critical, digitalSignature
            extendedKeyUsage = serverAuth
            subjectAltName = @centralcloud_sans

            [ centralcloud_sans ]
            DNS.1 = {$fqdn}
            URI.1 = spiffe://centralcloud/node/{$nodeId}
            CONF;
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            @unlink($path);
            throw new RuntimeException('certificate_signing_failed');
        }

        return $path;
    }

    private function assertProtectedFile(string $path, bool $private): void
    {
        if ($path === '' || ! is_file($path) || is_link($path) || ! is_readable($path)) {
            throw new RuntimeException('certificate_issuer_not_configured');
        }
        $permissions = fileperms($path);
        if ($private && ($permissions === false || ($permissions & 0o077) !== 0)) {
            throw new RuntimeException('certificate_issuer_insecure_permissions');
        }
    }

    private function read(string $path): string
    {
        $value = file_get_contents($path);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException('certificate_issuer_not_configured');
        }

        return $value;
    }

    /** @return list<string> */
    private function parseSans(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }
}
