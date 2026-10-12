<?php

namespace App\Actions;

use App\Jobs\PromoteWaitlist;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class CancelBooking
{
    public function handle(Booking $booking): Booking
    {
        $session = $booking->session;
        $window = $session->classType->studio->late_cancel_minutes;
        $late = now()->diffInMinutes($session->starts_at, false) < $window;

        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        if ($late) {
            Log::warning('Late cancellation: class credit used', ['booking' => $booking->id, 'minutes_before' => (int) now()->diffInMinutes($session->starts_at, false)]);
        } else {
            Log::info('Booking cancelled', ['booking' => $booking->id, 'session' => $session->id]);
        }

        if ($session->waitlist()->whereNull('promoted_at')->exists()) {
            PromoteWaitlist::dispatch($session)->onQueue('waitlist');
        }

        return $booking;
    }
}
