<?php

declare(strict_types=1);

namespace FernwayDemo;

use App\Jobs\SyncStripeCustomer;
use App\Models\User;
use Illuminate\Console\Command;

/** Queues the Stripe customer sync of a member, as the checkout of US-010 does. */
class DispatchCommand extends Command
{
    protected $signature = 'fernway:demo-dispatch {member}';

    public function handle(): int
    {
        SyncStripeCustomer::dispatch(User::findOrFail((int) $this->argument('member')));

        return self::SUCCESS;
    }
}
