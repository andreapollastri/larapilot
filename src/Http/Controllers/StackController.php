<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Larapilot\Services\CheckpointService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\ProjectStackService;
use Larapilot\Services\SbomService;
use Larapilot\Services\VendorAuditService;

/**
 * The pages about what the project is made of: About (the stack and its
 * support windows), SBOM (every dependency, its license, and its known
 * vulnerabilities), and Checkpoint under Security.
 */
class StackController
{
    public function __construct(
        protected ConfigService $config,
        protected ProjectStackService $stack,
        protected SbomService $sbom,
        protected VendorAuditService $audit,
        protected CheckpointService $checkpoint,
    ) {}

    public function about(): View
    {
        $this->guard();

        return view('larapilot::dashboard.about', [
            'facts' => $this->stack->facts(),
        ]);
    }

    public function sbom(): View
    {
        $this->guard();

        $inventory = $this->sbom->inventory();
        $audit = $this->audit->latest();

        return view('larapilot::dashboard.sbom', [
            'inventory' => $inventory,
            'audit' => $audit,
            'gate' => $audit !== null ? $this->audit->gate($audit) : null,
            'vulnerable' => $this->vulnerableIndex($audit),
        ]);
    }

    public function sbomMarkdown(): Response
    {
        $this->guard();

        return response($this->sbom->markdown($this->audit->latest()), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->sbom->markdownFilename().'"',
        ]);
    }

    public function sbomCyclonedx(): Response
    {
        $this->guard();

        return response((string) json_encode($this->sbom->cyclonedx($this->audit->latest()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/vnd.cyclonedx+json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->sbom->cyclonedxFilename().'"',
        ]);
    }

    public function vendorAuditReport(): Response
    {
        $this->guard();

        $audit = $this->audit->latest();

        if ($audit === null) {
            abort(404);
        }

        return response($this->audit->report($audit), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="vendor-audit-'.now()->format('Y-m-d').'.md"',
        ]);
    }

    /**
     * Ask OSV.dev now. The names and versions of the packages leave the
     * machine; nothing else does.
     */
    public function runVendorAudit(): RedirectResponse
    {
        $this->guard();
        @set_time_limit(180);

        try {
            $summary = $this->audit->check();
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('larapilot.dashboard.sbom')
                ->with('larapilot_error', $e->getMessage());
        }

        $open = $summary['open'];

        return redirect()
            ->route('larapilot.dashboard.sbom')
            ->with('larapilot_success', $open === 0
                ? 'Checked '.$summary['components'].' packages against OSV.dev: no known vulnerability.'
                : 'Checked '.$summary['components'].' packages against OSV.dev: '.$open.' open '.($open === 1 ? 'advisory' : 'advisories').'. Fix them with /larapilot-vendor-check.');
    }

    public function checkpoint(): View
    {
        $this->guard();

        return view('larapilot::dashboard.security-checkpoint', [
            'installed' => $this->checkpoint->installed(),
            'version' => $this->checkpoint->version(),
            'scan' => $this->checkpoint->latest(),
            'enforced' => $this->config->securityScanEnabled(),
        ]);
    }

    public function runCheckpoint(): RedirectResponse
    {
        $this->guard();
        @set_time_limit(300);

        try {
            $summary = $this->checkpoint->scan();
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('larapilot.dashboard.security.checkpoint')
                ->with('larapilot_error', $e->getMessage());
        }

        return redirect()
            ->route('larapilot.dashboard.security.checkpoint')
            ->with('larapilot_success', 'Checkpoint ran '.$summary['total'].' checks: '.$summary['counts']['pass'].' passed, '.$summary['counts']['warn'].' warnings, '.$summary['counts']['fail'].' failed.');
    }

    public function checkpointReport(): Response
    {
        $this->guard();

        $scan = $this->checkpoint->latest();

        if ($scan === null) {
            abort(404);
        }

        return response($this->checkpoint->report($scan), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="checkpoint-'.now()->format('Y-m-d').'.md"',
        ]);
    }

    /**
     * Advisory ids by component, for the inventory table.
     *
     * @param  array<string, mixed>|null  $audit
     * @return array<string, array{ids: list<string>, severity: string, open: int}>
     */
    protected function vulnerableIndex(?array $audit): array
    {
        $index = [];
        $rank = array_flip(VendorAuditService::SEVERITIES);

        foreach ($audit['findings'] ?? [] as $finding) {
            $key = $finding['ecosystem'].'|'.strtolower($finding['package']).'|'.$finding['version'];
            $index[$key] ??= ['ids' => [], 'severity' => 'unknown', 'open' => 0];
            $index[$key]['ids'][] = $finding['id'];
            $index[$key]['open'] += $finding['state'] !== 'waived' ? 1 : 0;

            if (($rank[$finding['severity']] ?? 9) < ($rank[$index[$key]['severity']] ?? 9)) {
                $index[$key]['severity'] = $finding['severity'];
            }
        }

        return $index;
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
