<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_render_the_billing_page_without_subscriptions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Facturation')
            ->assertSee('Aucun abonnement Stripe.');
    }
}
