<?php

use App\Actions\BookClass;
use App\Exceptions\ClassFullException;
use App\Models\ClassSession;
use App\Models\User;

it('books a member into a class with spots left', function () {
    $session = ClassSession::factory()->create(['capacity' => 2]);

    $booking = app(BookClass::class)->handle($session, User::factory()->create());

    expect($booking->status)->toBe('confirmed');
});

it('refuses a full class', function () {
    $session = ClassSession::factory()->hasBookings(2)->create(['capacity' => 2]);

    app(BookClass::class)->handle($session, User::factory()->create());
})->throws(ClassFullException::class);
