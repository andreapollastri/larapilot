<?php

namespace App\Actions;

use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class CheckInMember
{
    public function handle(Booking $booking): Booking
    {
        $booking->update(['checked_in_at' => now()]);
        Log::info('Member checked in', ['booking' => $booking->id, 'session' => $booking->class_session_id]);

        return $booking;
    }
}
