<?php

declare(strict_types=1);

namespace FernwayDemo;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Support\Totp;

/**
 * The team's week on the staging dashboard: who turned the authenticator on and when each person
 * last signed in — what a week of real sign-ins would have left in .larapilot/auth.yaml.
 * Maya, the admin the screenshots sign in as, keeps hers off so the sign-in needs no code.
 */
class TeamCommand extends Command
{
    protected $signature = 'fernway:demo-team';

    public function handle(DashboardAuthService $auth): int
    {
        $shift = (int) (getenv('DEMO_SHIFT_DAYS') ?: 0);
        $at = static fn (string $time): Carbon => Carbon::parse($time, 'UTC')->addDays($shift);

        foreach (['luca' => '2026-10-05 11:48', 'priya' => '2026-10-05 14:02'] as $user => $time) {
            Carbon::setTestNow($at($time));
            $auth->enableTotp($user, Totp::generateSecret());
        }
        foreach (['priya' => '2026-10-09 17:12', 'luca' => '2026-10-09 10:31', 'sam' => '2026-10-08 16:05', 'erin' => '2026-10-07 19:40'] as $user => $time) {
            Carbon::setTestNow($at($time));
            $auth->touchLogin($user);
        }
        Carbon::setTestNow();

        return self::SUCCESS;
    }
}
