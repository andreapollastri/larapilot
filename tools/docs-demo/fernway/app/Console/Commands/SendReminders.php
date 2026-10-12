<?php

namespace App\Console\Commands;

use App\Models\ClassSession;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'fernway:send-reminders';

    protected $description = 'Queue the reminders of the classes that start in two hours';

    public function handle(): int
    {
        $due = ClassSession::upcoming()->whereBetween('starts_at', [now()->addHours(2), now()->addHours(2)->addMinutes(15)])->count();
        $this->info("{$due} classes to remind.");

        return self::SUCCESS;
    }
}
