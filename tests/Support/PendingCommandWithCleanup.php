<?php

declare(strict_types=1);

namespace Larapilot\Tests\Support;

use Illuminate\Console\OutputStyle;
use Illuminate\Testing\PendingCommand;

/**
 * On Laravel 10, PendingCommand::run() binds a mocked OutputStyle into the
 * container and never removes it, so a later plain Artisan::call() in the
 * same test resolves the stale mock and Artisan::output() comes back empty.
 * Laravel 11+ removes the binding itself; this subclass backports that
 * cleanup so the suite behaves the same on every supported version.
 */
class PendingCommandWithCleanup extends PendingCommand
{
    public function run(): int
    {
        try {
            return parent::run();
        } finally {
            $this->app->offsetUnset(OutputStyle::class);
        }
    }
}
