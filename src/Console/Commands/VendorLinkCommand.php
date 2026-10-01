<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\VendorAuditService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SpecCode;

class VendorLinkCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:vendor-link
                            {ids : Comma-separated advisory ids of the last check, e.g. GHSA-wxmh-65f7-jcvw}
                            {--spec= : The spec that fixes them}
                            {--waive : Accept them as they are}
                            {--reason= : Why they are accepted (required with --waive)}
                            {--clear : Forget the decision}';

    protected $description = 'Record what was decided about vulnerable dependencies: the spec that fixes them, or a waiver with its reason';

    public function handle(VendorAuditService $audit): int
    {
        $spec = is_string($this->option('spec')) && trim($this->option('spec')) !== '' ? trim($this->option('spec')) : null;
        $waive = (bool) $this->option('waive');
        $clear = (bool) $this->option('clear');

        if ((int) ($spec !== null) + (int) $waive + (int) $clear !== 1) {
            return $this->failure('E_INVALID_INPUT', 'Choose one: --spec=CODE, --waive --reason="…", or --clear.', $this->exitForCode('E_INVALID_INPUT'));
        }

        if ($spec !== null && ! SpecCode::isValid($spec)) {
            return $this->failure('E_INVALID_INPUT', 'Invalid spec code: '.$spec, $this->exitForCode('E_INVALID_INPUT'));
        }

        try {
            $ids = $audit->decide(explode(',', (string) $this->argument('ids')), $spec, $waive ? (string) $this->option('reason') : null, $clear);
        } catch (\InvalidArgumentException $e) {
            return $this->failure('E_INVALID_INPUT', $e->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        return $this->success('vendor_link', [
            'ids' => $ids,
            'state' => $clear ? 'open' : ($spec !== null ? 'in_backlog' : 'waived'),
            'spec' => $spec !== null ? strtoupper($spec) : null,
            'reason' => $waive ? trim((string) $this->option('reason')) : null,
        ]);
    }
}
