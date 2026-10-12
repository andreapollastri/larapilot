<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $event = $request->json()->all();

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $payment = Payment::where('provider_reference', $event['data']['object']['id'] ?? '')->first();
            $payment?->update(['status' => 'succeeded', 'paid_at' => now()]);
            Log::info('Stripe checkout completed', ['payment' => $payment?->id]);
        }

        return response()->json(['received' => true]);
    }
}
