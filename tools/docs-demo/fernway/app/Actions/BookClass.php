<?php

namespace App\Actions;

use App\Exceptions\ClassFullException;
use App\Jobs\SendClassReminder;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookClass
{
    public function handle(ClassSession $session, User $member): Booking
    {
        $booking = DB::transaction(function () use ($session, $member): Booking {
            $booked = $session->bookings()->where('status', 'confirmed')->lockForUpdate()->count();
            if ($booked >= $session->capacity) {
                throw ClassFullException::for($session, $booked);
            }

            return $session->bookings()->create(['user_id' => $member->id, 'status' => 'confirmed']);
        });

        $left = $session->spotsLeft();
        Log::info('Booking confirmed', ['booking' => $booking->id, 'session' => $session->id, 'member' => $member->id, 'spots_left' => $left]);
        if ($left > 0 && $left <= 2) {
            Log::warning('Class nearly full', ['session' => $session->id, 'spots_left' => $left]);
        }

        Mail::to($member)->send(new BookingConfirmed($booking));
        SendClassReminder::dispatch($booking)->delay($session->starts_at->copy()->subHours(2));

        return $booking;
    }
}
