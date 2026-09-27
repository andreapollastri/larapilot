<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class AikidoIssuesCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:aikido-issues
                            {--severity= : Lowest severity to list: critical, high, medium, or low}
                            {--type= : One kind of finding: open_source, leaked_secret, sast, iac, …}
                            {--new : Only the findings nobody decided about}
                            {--limit= : List at most this many, the most severe first}
                            {--report : Write the findings to {paths.security}/aikido.md}
                            {--gate : Exit 1 when the verdict of the gate is FAIL}';

    protected $description = 'Download the open findings of Aikido for this repository, with what was decided about each';

    public function handle(AikidoService $aikido, ConfigService $config): int
    {
        if (! $config->aikidoEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Aikido is off for this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --aikido=YES'
            );
        }

        try {
            // One download: the report is about every finding, the list
            // about the ones that were asked for.
            $all = $aikido->findings();
            $findings = $aikido->findings([
                'severity' => $this->option('severity'),
                'type' => $this->option('type'),
                'new' => (bool) $this->option('new'),
                'limit' => is_numeric($this->option('limit')) ? (int) $this->option('limit') : null,
            ], false);
        } catch (AikidoException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            return $this->failure('E_INVALID_INPUT', $e->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        $findings['report'] = $this->option('report') ? $aikido->writeReport($all)['relative'] : null;

        $this->success('aikido_issues', $findings);

        return $this->option('gate') && $findings['gate']['verdict'] === 'FAIL' ? self::FAILURE : self::SUCCESS;
    }
}
