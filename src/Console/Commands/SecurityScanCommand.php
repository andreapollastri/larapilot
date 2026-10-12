<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SecurityService;
use Larapilot\Support\SecurityCommand;
use Throwable;

class SecurityScanCommand extends SecurityCommand
{
    protected $signature = 'larapilot:security-scan
                            {--profile= : Hackly only: quick, standard (default), or deep}
                            {--include-targets : Hackly only: deep-scan the domains linked to the repository too}';

    protected $description = 'Ask the scanner (Aikido or Hackly) to scan this repository again; the findings change when it is done';

    public function handle(SecurityService $security): int
    {
        if (($off = $this->scannerOff($security)) !== null) {
            return $off;
        }

        try {
            $profile = trim((string) $this->option('profile'));

            return $this->success('security_scan', $security->scan(
                $profile !== '' ? $profile : null,
                $this->option('include-targets') ? true : null
            ));
        } catch (Throwable $e) {
            return $this->failed($security, $e);
        }
    }
}
