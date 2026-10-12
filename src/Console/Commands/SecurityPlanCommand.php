<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SecurityService;
use Larapilot\Support\SecurityCommand;
use Throwable;

class SecurityPlanCommand extends SecurityCommand
{
    protected $signature = 'larapilot:security-plan
                            {--ids= : The confirmed findings, separated by commas}';

    protected $description = 'Group confirmed security findings by kind and fix, so each group becomes one triage handoff (a leaked secret never merges)';

    public function handle(SecurityService $security): int
    {
        if (($off = $this->scannerOff($security)) !== null) {
            return $off;
        }

        try {
            return $this->success('security_plan', $security->plan([(string) $this->option('ids')]));
        } catch (Throwable $e) {
            return $this->failed($security, $e);
        }
    }
}
