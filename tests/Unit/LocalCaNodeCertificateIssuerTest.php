<?php

namespace Tests\Unit;

use App\Enums\NodeEnrollmentMode;
use App\Enums\NodeEnrollmentStatus;
use App\Enums\NodeEnrollmentStep;
use App\Enums\NodeStatus;
use App\Models\Node;
use App\Models\NodeEnrollment;
use App\Services\Certificates\LocalCaNodeCertificateIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalCaNodeCertificateIssuerTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_issuer_accepts_exact_sans_and_never_needs_private_node_key(): void
    {
        $directory = sys_get_temp_dir().'/centralcloud-ca-'.Str::random(12);
        mkdir($directory, 0700);
        try {
            [$caCert, $caKey] = $this->ca($directory);
            file_put_contents($directory.'/ca.crt', $caCert);
            file_put_contents($directory.'/ca.key', $caKey);
            file_put_contents($directory.'/client-ca.crt', $caCert);
            chmod($directory.'/ca.key', 0600);
            config()->set('centralcloud.enrollment.ca_cert_path', $directory.'/ca.crt');
            config()->set('centralcloud.enrollment.ca_key_path', $directory.'/ca.key');
            config()->set('centralcloud.enrollment.control_plane_client_ca_path', $directory.'/client-ca.crt');

            $node = Node::create([
                'uuid' => (string) Str::uuid(), 'agent_node_id' => (string) Str::uuid(), 'name' => 'node',
                'endpoint' => 'https://node.example.com:9443', 'status' => NodeStatus::Provisioning,
            ]);
            $enrollment = NodeEnrollment::create([
                'uuid' => (string) Str::uuid(), 'node_id' => $node->id, 'status' => NodeEnrollmentStatus::Approved,
                'mode' => NodeEnrollmentMode::Interactive, 'agent_fqdn' => 'node.example.com',
                'step' => NodeEnrollmentStep::TLS, 'correlation_id' => (string) Str::uuid(), 'expires_at' => now()->addMinutes(10),
            ])->load('node');
            [$csr] = $this->nodeCsr('node.example.com', $node->agent_node_id, $directory);

            $issued = (new LocalCaNodeCertificateIssuer)->issue($enrollment, $csr);

            $parsed = openssl_x509_parse($issued->certificate, false);
            $this->assertStringContainsString('DNS:node.example.com', $parsed['extensions']['subjectAltName']);
            $this->assertStringContainsString('spiffe://centralcloud/node/'.$node->agent_node_id, $parsed['extensions']['subjectAltName']);
            $this->assertStringNotContainsString('PRIVATE KEY', $issued->certificate.$issued->chain.$issued->clientCa);
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($directory);
        }
    }

    private function ca(string $directory): array
    {
        $config = <<<'CONF'
            [ v3_ca ]
            basicConstraints = critical, CA:TRUE
            keyUsage = critical, keyCertSign, cRLSign
            subjectKeyIdentifier = hash
            CONF;
        file_put_contents($directory.'/ca-openssl.cnf', $config);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $csr = openssl_csr_new(['commonName' => 'CentralCloud Test CA'], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $key, 3650, [
            'config' => $directory.'/ca-openssl.cnf',
            'digest_alg' => 'sha256',
            'x509_extensions' => 'v3_ca',
        ]);
        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($key, $keyPem);

        return [$certificatePem, $keyPem];
    }

    private function nodeCsr(string $fqdn, string $nodeID, string $directory): array
    {
        $config = <<<CONF
[ req ]
distinguished_name = dn
req_extensions = v3_req
prompt = no
[ dn ]
CN = {$fqdn}
[ v3_req ]
subjectAltName = @alt_names
[ alt_names ]
DNS.1 = {$fqdn}
URI.1 = spiffe://centralcloud/node/{$nodeID}
CONF;
        file_put_contents($directory.'/openssl.cnf', $config);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $csr = openssl_csr_new(['commonName' => $fqdn], $key, ['config' => $directory.'/openssl.cnf', 'req_extensions' => 'v3_req', 'digest_alg' => 'sha256']);
        openssl_csr_export($csr, $csrPem);
        openssl_pkey_export($key, $keyPem);

        return [$csrPem, $keyPem];
    }
}
