<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SecurityService;
use Larapilot\Support\SecurityCommand;
use Throwable;

class SecurityPushCommand extends SecurityCommand
{
    protected $signature = 'larapilot:security-push';

    protected $description = 'Tell the scanner (Aikido or Hackly) every decision about an open finding it was not told yet';

    public function handle(SecurityService $security): int
    {
        if (($off = $this->scannerOff($security)) !== null) {
            return $off;
        }

        try {
            return $this->success('security_push', $security->push());
        } catch (Throwable $e) {
            return $this->failed($security, $e);
        }
    }
}
