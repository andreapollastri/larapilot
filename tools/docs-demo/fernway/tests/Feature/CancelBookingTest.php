<?php

use App\Actions\CancelBooking;
use App\Jobs\PromoteWaitlist;
use App\Models\Booking;
use Illuminate\Support\Facades\Queue;

it('promotes the waitlist when a booking is cancelled', function () {
    Queue::fake();
    $booking = Booking::factory()->withWaitlist()->create();

    app(CancelBooking::class)->handle($booking);

    Queue::assertPushedOn('waitlist', PromoteWaitlist::class);
});

it('uses the class credit inside the late window', function () {
    $booking = Booking::factory()->startingIn(minutes: 45)->create();

    app(CancelBooking::class)->handle($booking);

    expect($booking->fresh()->status)->toBe('cancelled');
});
