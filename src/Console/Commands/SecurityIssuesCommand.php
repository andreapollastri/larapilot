<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SecurityService;
use Larapilot\Support\SecurityCommand;
use Throwable;

class SecurityIssuesCommand extends SecurityCommand
{
    protected $signature = 'larapilot:security-issues
                            {--severity= : Lowest severity to list: critical (Aikido), high, medium, or low}
                            {--type= : One kind of finding, as the scanner names it}
                            {--new : Only the findings nobody decided about}
                            {--limit= : List at most this many, the most severe first}
                            {--detail= : One finding as a brief and a fix prompt, by its id}
                            {--report : Write the findings to the security folder of the docs, as security-report.md}
                            {--gate : Exit 1 when the verdict of the gate is FAIL}';

    protected $description = 'Download the open security findings of this repository from Aikido or Hackly, with what was decided about each';

    public function handle(SecurityService $security): int
    {
        if (($off = $this->scannerOff($security)) !== null) {
            return $off;
        }

        try {
            $detail = trim((string) $this->option('detail'));

            if ($detail !== '') {
                return $this->success('security_detail', $security->detail($detail));
            }

            $all = $security->findings();
            $findings = $security->findings([
                'severity' => $this->option('severity'),
                'type' => $this->option('type'),
                'new' => (bool) $this->option('new'),
                'limit' => is_numeric($this->option('limit')) ? (int) $this->option('limit') : null,
            ], false);
            $findings['report'] = $this->option('report') ? $security->writeReport($all)['relative'] : null;
        } catch (Throwable $e) {
            return $this->failed($security, $e);
        }

        $this->success('security_issues', $findings);

        return $this->option('gate') && $findings['gate']['verdict'] === 'FAIL' ? self::FAILURE : self::SUCCESS;
    }
}
