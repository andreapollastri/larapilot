<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\VendorAuditService;
use Larapilot\Support\LarapilotCommand;

class VendorAuditCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:vendor-audit
                            {--cached : Answer the last check instead of asking OSV.dev again}
                            {--new : Only the findings nobody decided about}
                            {--limit=30 : List at most this many findings, the most severe first}
                            {--report : Write the findings to {paths.security}/vendor-audit.md}
                            {--fail-on=high : Lowest severity that fails the gate: critical, high, medium, low, or none}
                            {--gate : Exit 1 when the verdict of the gate is FAIL}';

    protected $description = 'Check every dependency of the SBOM against OSV.dev for known vulnerabilities (CVE, GHSA), with what was decided about each';

    public function handle(VendorAuditService $audit): int
    {
        $failOn = strtolower((string) $this->option('fail-on'));

        if (! in_array($failOn, ['critical', 'high', 'medium', 'low', 'none'], true)) {
            return $this->failure('E_INVALID_INPUT', '--fail-on takes critical, high, medium, low, or none.', $this->exitForCode('E_INVALID_INPUT'));
        }

        if ($this->option('cached')) {
            $summary = $audit->latest();

            if ($summary === null) {
                return $this->failure('E_NOT_FOUND', 'No vulnerability check was made yet.', $this->exitForCode('E_NOT_FOUND'), 'Run: php artisan larapilot:vendor-audit');
            }
        } else {
            try {
                $summary = $audit->check();
            } catch (\RuntimeException $e) {
                return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), 'OSV.dev needs no key; check the network, or answer the last check with --cached.');
            }
        }

        $gate = $audit->gate($summary, $failOn);
        $report = $this->option('report') ? $audit->writeReport($summary)['relative'] : null;
        $findings = $summary['findings'];

        if ($this->option('new')) {
            $findings = array_values(array_filter($findings, static fn (array $finding): bool => $finding['state'] === 'open'));
        }

        $limit = max(1, (int) $this->option('limit'));

        $this->success('vendor_audit', [
            'checked_at' => $summary['checked_at'],
            'source' => $summary['source'],
            'components' => $summary['components'],
            'stale' => $summary['stale'],
            'counts' => $summary['counts'],
            'states' => $summary['states'],
            'total' => $summary['total'],
            'gate' => $gate,
            'packages' => $summary['packages'],
            'findings' => array_slice($findings, 0, $limit),
            'truncated' => count($findings) > $limit,
            'ledger' => $summary['ledger'],
            'report' => $report,
        ]);

        return $this->option('gate') && $gate['verdict'] === 'FAIL' ? self::FAILURE : self::SUCCESS;
    }
}
