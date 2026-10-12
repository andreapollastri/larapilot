<?php

namespace App\Jobs;

use App\Exceptions\PaymentWebhookException;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SyncStripeCustomer implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public User $member) {}

    public function handle(): void
    {
        $response = Http::withToken((string) config('services.stripe.secret'))
            ->asForm()
            ->post('https://api.stripe.com/v1/customers', ['email' => $this->member->email, 'name' => $this->member->name]);

        if ($response->failed()) {
            throw PaymentWebhookException::stripe($response->status(), (string) $response->json('error.message'), $this->member->id);
        }
    }
}
