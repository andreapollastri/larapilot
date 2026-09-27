<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\PrdImpactService;
use Larapilot\Services\PrdService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\PrdIds;

class PrdImpactCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:prd-impact
                            {--ids= : Comma-separated PRD ids (FR-004,J-001,NFR-002,Q-001); omit to trace the whole PRD}';

    protected $description = 'Trace PRD ids to the specs that cite them, with the action each spec needs by status';

    public function handle(PrdService $prd, PrdImpactService $impact): int
    {
        if (! $prd->exists()) {
            return $this->failure(
                'E_PRECONDITION',
                'PRD file does not exist.',
                $this->exitForCode('E_PRECONDITION'),
                'Run larapilot-inception or larapilot-adopt first.'
            );
        }

        $ids = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('ids'))
        ), static fn (string $id): bool => $id !== ''));

        $invalid = array_values(array_filter($ids, static fn (string $id): bool => ! PrdIds::isValid($id)));

        if ($invalid !== []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Invalid PRD id: '.implode(', ', $invalid).'.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Use FR-, J-, NFR-, or Q- ids as written in the PRD, e.g. --ids=FR-004,J-001.'
            );
        }

        return $this->success('prd_impact', $impact->analyse($ids));
    }
}
