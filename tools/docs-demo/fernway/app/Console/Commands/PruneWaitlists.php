<?php

namespace App\Console\Commands;

use App\Models\WaitlistEntry;
use Illuminate\Console\Command;

class PruneWaitlists extends Command
{
    protected $signature = 'fernway:prune-waitlists';

    protected $description = 'Drop the waitlist entries of classes that already started';

    public function handle(): int
    {
        $pruned = WaitlistEntry::whereNull('promoted_at')
            ->whereHas('session', fn ($query) => $query->where('starts_at', '<', now()))
            ->delete();
        $this->info("{$pruned} entries pruned.");

        return self::SUCCESS;
    }
}
