<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_signature_is_rejected(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);
        $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'invalid'], json_encode(['id' => 'evt_bad']))->assertStatus(400);
        $this->assertDatabaseCount('stripe_events', 0);
    }

    public function test_same_signed_event_is_stored_only_once(): void
    {
        $secret = 'whsec_test';
        config(['cashier.webhook.secret' => $secret]);
        $payload = json_encode(['id' => 'evt_unique_1', 'object' => 'event', 'type' => 'test.event', 'data' => ['object' => ['id' => 'obj_1', 'object' => 'test']]]);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)->assertOk();
        }$this->assertDatabaseCount('stripe_events',1);
    }
}
