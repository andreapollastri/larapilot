<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BookingConfirmed extends Mailable
{
    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $session = $this->booking->session;

        return new Envelope(subject: "You're booked: {$session->classType->name}, {$session->starts_at->format('D j M, H:i')}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-confirmed');
    }
}
