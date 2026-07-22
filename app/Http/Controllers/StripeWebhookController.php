<?php

namespace App\Http\Controllers;

use App\Models\StripeEvent;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), (string) config('cashier.webhook.secret'));
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }
        StripeEvent::firstOrCreate(['stripe_event_id' => $event->id], ['type' => $event->type, 'payload' => $event->toArray(), 'status' => 'RECEIVED']);

        return response()->json(['received' => true]);
    }
}
