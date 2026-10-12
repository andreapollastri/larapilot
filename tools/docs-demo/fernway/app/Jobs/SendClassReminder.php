<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendClassReminder implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function handle(): void
    {
        if ($this->booking->fresh()?->status !== 'confirmed') {
            return;
        }

        Log::info('Class reminder sent', ['booking' => $this->booking->id]);
    }
}
