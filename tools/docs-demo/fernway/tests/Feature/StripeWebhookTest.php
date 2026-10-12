<?php

use App\Models\Payment;

it('marks a payment paid on checkout.session.completed', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    $this->postJson(route('stripe.webhook'), [
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => $payment->provider_reference]],
    ])->assertOk();

    expect($payment->fresh()->status)->toBe('succeeded');
});
