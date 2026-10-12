<?php

namespace App\Jobs;

use App\Mail\WaitlistPromoted;
use App\Models\ClassSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PromoteWaitlist implements ShouldQueue
{
    use Queueable;

    public function __construct(public ClassSession $session) {}

    public function handle(): void
    {
        $entry = $this->session->waitlist()->whereNull('promoted_at')->first();
        if ($entry === null || $this->session->spotsLeft() === 0) {
            return;
        }

        dump($entry->only(['id', 'class_session_id', 'user_id', 'position']));

        $booking = $this->session->bookings()->updateOrCreate(['user_id' => $entry->user_id], ['status' => 'confirmed', 'cancelled_at' => null]);
        $entry->update(['promoted_at' => now()]);

        Log::info('Waitlist promotion', ['session' => $this->session->id, 'member' => $entry->user_id, 'position' => $entry->position]);
        Mail::to($entry->user)->send(new WaitlistPromoted($booking));
    }
}
