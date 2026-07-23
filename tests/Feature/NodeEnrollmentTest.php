<?php

namespace Tests\Feature;

use App\Contracts\NodeCertificateIssuer;
use App\Enums\NodeEnrollmentStatus;
use App\Enums\NodeStatus;
use App\Models\NodeEnrollment;
use App\Models\User;
use App\Services\Enrollment\EnrollmentException;
use App\Services\Enrollment\NodeEnrollmentService;
use App\ValueObjects\IssuedNodeCertificate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class NodeEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('centralcloud.enrollment.hash_key', 'test-hmac-key-that-is-long-and-random');
        config()->set('centralcloud.enrollment.agent_manifest_url', 'https://releases.example.test/manifest.json');
        config()->set('centralcloud.enrollment.source_cidrs', ['203.0.113.0/24']);
    }

    public function test_device_enrollment_returns_readable_codes_and_stores_only_hashes(): void
    {
        $response = $this->postJson('/api/v1/node-enrollments/device', [
            ...$this->metadata(),
            'requested_channel' => 'beta',
        ]);

        $response->assertCreated()->assertJsonStructure([
            'enrollment_id', 'device_code', 'user_code', 'verification_uri',
            'verification_uri_complete', 'expires_in', 'interval', 'correlation_id',
        ]);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $response->json('user_code'));
        $enrollment = NodeEnrollment::firstOrFail();
        $this->assertNotSame($response->json('device_code'), $enrollment->device_code_hash);
        $this->assertNotSame(str_replace('-', '', $response->json('user_code')), $enrollment->user_code_hash);
        $this->assertSame(64, strlen($enrollment->device_code_hash));
        $this->assertSame('beta', $enrollment->agent_channel);
    }

    public function test_polling_reports_pending_then_slow_down_without_exposing_secrets(): void
    {
        $created = $this->postJson('/api/v1/node-enrollments/device', $this->metadata())->assertCreated();
        $payload = ['device_code' => $created->json('device_code')];

        $this->postJson('/api/v1/node-enrollments/device/token', $payload)
            ->assertStatus(400)->assertJson(['error' => 'authorization_pending']);
        $this->postJson('/api/v1/node-enrollments/device/token', $payload)
            ->assertStatus(429)->assertJson(['error' => 'slow_down'])
            ->assertJsonMissing(['device_code' => $payload['device_code']]);
    }

    public function test_approval_reserves_one_provisioning_node_and_delivers_bootstrap_once(): void
    {
        $service = app(NodeEnrollmentService::class);
        $created = $service->createDevice($this->metadata());
        $enrollment = $service->claim($created['user_code'], User::factory()->create()->id);
        $service->approve($enrollment, $this->configuration());
        NodeEnrollment::whereKey($enrollment->id)->update(['last_polled_at' => null]);

        $approved = $service->poll($created['device_code']);

        $this->assertSame('approved', $approved['status']);
        $this->assertGreaterThanOrEqual(32, strlen($approved['bootstrap_token']));
        $this->assertSame('bearer', $approved['agent']['authentication']['mode']);
        $this->assertGreaterThanOrEqual(32, strlen($approved['agent']['authentication']['token']));
        $this->assertSame(NodeStatus::Provisioning, $enrollment->fresh()->node->status);
        $this->assertSame('bearer', $enrollment->fresh()->node->agent_auth_mode);
        $this->assertNotSame($approved['agent']['authentication']['token'], $enrollment->fresh()->node->getRawOriginal('agent_token'));
        $this->assertArrayNotHasKey('agent_token', $enrollment->fresh()->node->toArray());
        $this->assertSame(1, $enrollment->fresh()->node()->count());
        NodeEnrollment::whereKey($enrollment->id)->update(['last_polled_at' => null]);
        $this->expectExceptionMessage('Bootstrap token was already delivered');
        $service->poll($created['device_code']);
    }

    public function test_automatic_token_is_one_time_and_progress_is_monotonic_and_idempotent(): void
    {
        $service = app(NodeEnrollmentService::class);
        $created = $service->createAutomatic($this->configuration());
        $approved = $service->exchangeAutomatic($created['token'], $this->metadata());
        $enrollment = $created['enrollment']->fresh();
        $key = (string) Str::uuid();

        $first = $service->progress($enrollment, $approved['bootstrap_token'], $key, ['step' => 'docker', 'percentage' => 25, 'message' => 'Docker prêt']);
        $replayed = $service->progress($enrollment, $approved['bootstrap_token'], $key, ['step' => 'docker', 'percentage' => 25, 'message' => 'Docker prêt']);
        $this->assertSame($first, $replayed);
        $this->assertSame(NodeEnrollmentStatus::Installing, $enrollment->fresh()->status);

        $this->expectExceptionMessage('Progress cannot move backwards');
        $service->progress($enrollment, $approved['bootstrap_token'], (string) Str::uuid(), ['step' => 'packages', 'percentage' => 10, 'message' => 'Retour']);
    }

    public function test_certificate_issuer_receives_only_csr_and_replay_is_safe(): void
    {
        $fake = new class implements NodeCertificateIssuer
        {
            public string $csr = '';

            public function issue(NodeEnrollment $enrollment, string $csr): IssuedNodeCertificate
            {
                $this->csr = $csr;

                return new IssuedNodeCertificate('PUBLIC CERT', 'PUBLIC CHAIN', 'CLIENT CA', '01', CarbonImmutable::now()->addDays(90));
            }
        };
        $this->app->instance(NodeCertificateIssuer::class, $fake);
        $service = app(NodeEnrollmentService::class);
        $created = $service->createAutomatic($this->configuration());
        $approved = $service->exchangeAutomatic($created['token'], $this->metadata());
        $created['enrollment']->fresh()->node->update(['agent_auth_mode' => null]);
        $key = (string) Str::uuid();
        $payload = ['csr' => "-----BEGIN CERTIFICATE REQUEST-----\nTEST\n-----END CERTIFICATE REQUEST-----"];

        $first = $service->certificate($created['enrollment']->fresh(), $approved['bootstrap_token'], $key, $payload);
        $second = $service->certificate($created['enrollment']->fresh(), $approved['bootstrap_token'], $key, $payload);

        $this->assertSame($first, $second);
        $this->assertSame($payload['csr'], $fake->csr);
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode($first));
    }

    public function test_completion_marks_ready_only_after_dashboard_bearer_https_probe(): void
    {
        $service = app(NodeEnrollmentService::class);
        $created = $service->createAutomatic($this->configuration());
        $approved = $service->exchangeAutomatic($created['token'], $this->metadata());
        $node = $created['enrollment']->fresh()->node;
        Http::fake([
            '*/v1/ready' => Http::response(['node_id' => $node->agent_node_id, 'agent_version' => '1.2.0', 'protocol_version' => '1', 'status' => 'ready']),
            '*/v1/resources' => Http::response(['node_id' => $node->agent_node_id, 'cpu_count' => 4, 'memory_total_bytes' => 8 << 30, 'memory_available_bytes' => 6 << 30, 'disk_total_bytes' => 100 << 30, 'disk_available_bytes' => 90 << 30]),
        ]);
        $payload = [
            'agent_identity' => $node->agent_node_id,
            'agent_version' => '1.2.0',
            'protocol_version' => '1',
            'services' => ['docker' => 'ok', 'postgresql' => 'ok', 'traefik' => 'ok', 'agent' => 'ok'],
            'healthcheck' => 'ok',
            'resources' => ['memory_bytes' => 8 << 30, 'disk_bytes' => 100 << 30],
            'validations' => [['name' => 'agent', 'status' => 'ok']],
        ];

        $key = (string) Str::uuid();
        $result = $service->complete($created['enrollment']->fresh(), $approved['bootstrap_token'], $key, $payload);
        $replayed = $service->complete($created['enrollment']->fresh(), $approved['bootstrap_token'], $key, $payload);

        $this->assertSame('ready', $result['body']['status']);
        $this->assertSame($result, $replayed);
        $this->assertSame(NodeStatus::Ready, $node->fresh()->status);
        $this->assertSame(NodeEnrollmentStatus::Ready, $created['enrollment']->fresh()->status);
        $this->assertSame(1, $created['enrollment']->fresh()->node()->count());
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer '.$approved['agent']['authentication']['token']));
    }

    public function test_failed_dashboard_probe_leaves_node_validating(): void
    {
        $service = app(NodeEnrollmentService::class);
        $created = $service->createAutomatic($this->configuration());
        $approved = $service->exchangeAutomatic($created['token'], $this->metadata());
        $node = $created['enrollment']->fresh()->node;
        Http::fake(['*' => Http::response([], 503)]);

        $result = $service->complete($created['enrollment']->fresh(), $approved['bootstrap_token'], (string) Str::uuid(), [
            'agent_identity' => $node->agent_node_id, 'agent_version' => '1.2.0', 'protocol_version' => '1',
            'services' => ['docker' => 'ok', 'postgresql' => 'ok', 'traefik' => 'ok', 'agent' => 'ok'],
            'healthcheck' => 'ok',
            'resources' => ['memory_bytes' => 8 << 30, 'disk_bytes' => 100 << 30],
            'validations' => [['name' => 'agent', 'status' => 'ok']],
        ]);

        $this->assertSame('validating', $result['body']['status']);
        $this->assertSame(NodeStatus::Validating, $node->fresh()->status);
        $this->assertSame('agent_unreachable', $created['enrollment']->fresh()->error_code);
    }

    public function test_failed_installer_report_is_rejected_before_dashboard_probe(): void
    {
        Http::fake();
        $service = app(NodeEnrollmentService::class);
        $created = $service->createAutomatic($this->configuration());
        $approved = $service->exchangeAutomatic($created['token'], $this->metadata());
        $node = $created['enrollment']->fresh()->node;

        try {
            $service->complete($created['enrollment']->fresh(), $approved['bootstrap_token'], (string) Str::uuid(), [
                'agent_identity' => $node->agent_node_id,
                'agent_version' => '1.2.0',
                'protocol_version' => '1',
                'services' => ['docker' => 'ok', 'postgresql' => 'error', 'traefik' => 'ok', 'agent' => 'ok'],
                'healthcheck' => 'ok',
                'resources' => ['memory_bytes' => 8 << 30, 'disk_bytes' => 100 << 30],
                'validations' => [['name' => 'postgresql', 'status' => 'error']],
            ]);
            $this->fail('Failed installer report was accepted.');
        } catch (EnrollmentException $exception) {
            $this->assertSame('validation_failed', $exception->errorCode);
        }

        Http::assertNothingSent();
        $this->assertSame(NodeStatus::Provisioning, $node->fresh()->status);
    }

    public function test_expired_denied_and_unknown_device_codes_have_stable_errors(): void
    {
        $service = app(NodeEnrollmentService::class);
        $expired = $service->createDevice($this->metadata());
        NodeEnrollment::firstOrFail()->update(['expires_at' => now()->subSecond()]);

        try {
            $service->poll($expired['device_code']);
            $this->fail('Expired device code was accepted.');
        } catch (EnrollmentException $exception) {
            $this->assertSame('expired_token', $exception->errorCode);
            $this->assertSame(NodeEnrollmentStatus::Expired, NodeEnrollment::firstOrFail()->fresh()->status);
        }

        try {
            $service->poll(str_repeat('x', 48));
            $this->fail('Unknown device code was accepted.');
        } catch (EnrollmentException $exception) {
            $this->assertSame('expired_token', $exception->errorCode);
        }

        NodeEnrollment::query()->delete();
        $denied = $service->createDevice($this->metadata());
        $enrollment = NodeEnrollment::firstOrFail();
        $service->deny($enrollment);
        $enrollment->update(['last_polled_at' => null]);

        try {
            $service->poll($denied['device_code']);
            $this->fail('Denied enrollment was accepted.');
        } catch (EnrollmentException $exception) {
            $this->assertSame('access_denied', $exception->errorCode);
            $this->assertDatabaseHas('audit_logs', ['action' => 'node_enrollment.denied']);
        }
    }

    public function test_automatic_and_bootstrap_tokens_cannot_be_replayed_after_consumption_or_revocation(): void
    {
        $service = app(NodeEnrollmentService::class);
        $created = $service->createAutomatic($this->configuration());
        $approved = $service->exchangeAutomatic($created['token'], $this->metadata());

        try {
            $service->exchangeAutomatic($created['token'], $this->metadata());
            $this->fail('Automatic token replay was accepted.');
        } catch (EnrollmentException $exception) {
            $this->assertSame('invalid_token', $exception->errorCode);
        }

        $service->revoke($created['enrollment']->fresh());
        try {
            $service->progress($created['enrollment']->fresh(), $approved['bootstrap_token'], (string) Str::uuid(), [
                'step' => 'packages', 'percentage' => 10, 'message' => 'Packages',
            ]);
            $this->fail('Revoked bootstrap token was accepted.');
        } catch (EnrollmentException $exception) {
            $this->assertSame('invalid_token', $exception->errorCode);
            $this->assertSame(NodeEnrollmentStatus::Revoked, $created['enrollment']->fresh()->status);
            $this->assertNull($created['enrollment']->fresh()->node->agent_token);
        }
    }

    public function test_progress_redacts_authorization_password_and_private_keys(): void
    {
        $service = app(NodeEnrollmentService::class);
        $created = $service->createAutomatic($this->configuration());
        $approved = $service->exchangeAutomatic($created['token'], $this->metadata());
        $secret = 'very-secret-bootstrap-value';

        $service->progress($created['enrollment']->fresh(), $approved['bootstrap_token'], (string) Str::uuid(), [
            'step' => 'packages',
            'percentage' => 10,
            'message' => "Authorization: Bearer {$secret} password=hunter2 -----BEGIN PRIVATE KEY-----\n{$secret}\n-----END PRIVATE KEY-----",
        ]);

        $stored = (string) $created['enrollment']->fresh()->public_message;
        $this->assertStringNotContainsString($secret, $stored);
        $this->assertStringNotContainsString('hunter2', $stored);
        $this->assertStringContainsString('[REDACTED]', $stored);
    }

    public function test_cleanup_expires_pending_enrollments_and_removes_their_codes(): void
    {
        app(NodeEnrollmentService::class)->createDevice($this->metadata());
        NodeEnrollment::firstOrFail()->update([
            'expires_at' => now()->subMinute(),
            'bootstrap_token_hash' => str_repeat('a', 64),
            'bootstrap_expires_at' => now()->subSecond(),
        ]);

        $this->artisan('centralcloud:enrollments:cleanup')->assertSuccessful();

        $enrollment = NodeEnrollment::firstOrFail()->fresh();
        $this->assertSame(NodeEnrollmentStatus::Expired, $enrollment->status);
        $this->assertNull($enrollment->device_code_hash);
        $this->assertNull($enrollment->user_code_hash);
        $this->assertNull($enrollment->bootstrap_token_hash);

        $automatic = app(NodeEnrollmentService::class)->createAutomatic($this->configuration());
        $automatic['enrollment']->update(['expires_at' => now()->subSecond()]);
        $this->artisan('centralcloud:enrollments:cleanup')->assertSuccessful();
        $this->assertSame(NodeEnrollmentStatus::Expired, $automatic['enrollment']->fresh()->status);
        $this->assertNull($automatic['enrollment']->fresh()->node->agent_token);
    }

    public function test_non_administrator_cannot_access_enrollment_admin_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/node-enrollments')
            ->assertForbidden();
    }

    private function metadata(): array
    {
        return [
            'hostname' => 'node-02', 'os' => 'debian', 'os_version' => '13', 'architecture' => 'amd64',
            'memory_bytes' => 8 << 30, 'disk_bytes' => 100 << 30, 'installer_version' => '1.0.0',
            'nonce' => '01JABCDEFGHIJKLMNOPQRSTUV', 'capabilities' => ['systemd', 'nftables'],
        ];
    }

    private function configuration(): array
    {
        return [
            'name' => 'node-02', 'environment' => 'production', 'region' => 'fr-par',
            'agent_fqdn' => 'node-02.nodes.example.com', 'agent_endpoint' => 'https://node-02.nodes.example.com',
            'published_address' => '203.0.113.42', 'agent_channel' => 'stable', 'agent_version' => '1.2.0',
            'initial_maintenance' => false, 'maximum_deployments' => 50,
        ];
    }
}
