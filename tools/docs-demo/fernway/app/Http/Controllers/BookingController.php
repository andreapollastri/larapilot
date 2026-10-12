<?php

namespace App\Http\Controllers;

use App\Actions\BookClass;
use App\Actions\CancelBooking;
use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController
{
    public function store(Request $request, ClassSession $session, BookClass $book): JsonResponse
    {
        $booking = $book->handle($session, $request->user());

        return response()->json(['booking' => $booking->id, 'status' => $booking->status], 201);
    }

    public function destroy(Request $request, Booking $booking, CancelBooking $cancel): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403);

        return response()->json(['booking' => $cancel->handle($booking)->status]);
    }
}
