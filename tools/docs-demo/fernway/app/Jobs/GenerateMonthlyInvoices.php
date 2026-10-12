<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $month = now()->subMonth();
        $count = Payment::where('status', 'succeeded')->whereBetween('paid_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->count();

        Log::info('Monthly invoices generated', ['month' => $month->format('Y-m'), 'payments' => $count]);
    }
}
