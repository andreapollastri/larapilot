<?php

namespace App\Http\Controllers;

use App\Actions\CheckInMember;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;

class CheckInController
{
    public function __invoke(Booking $booking, CheckInMember $checkIn): JsonResponse
    {
        return response()->json(['checked_in_at' => $checkIn->handle($booking)->checked_in_at]);
    }
}
