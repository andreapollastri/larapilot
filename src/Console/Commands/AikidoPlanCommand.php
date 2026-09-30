<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Aikido\AikidoException;
use Larapilot\Services\AikidoService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class AikidoPlanCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:aikido-plan
                            {--ids= : Comma-separated finding ids confirmed for resolution}';

    protected $description = 'Group the findings of Aikido the user confirmed by kind and fix, one triage handoff for each group';

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

        $raw = trim((string) $this->option('ids'));

        if ($raw === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Name the findings to plan.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Example: php artisan larapilot:aikido-plan --ids=24,31'
            );
        }

        $ids = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $part): bool => $part !== ''));

        try {
            $plan = $aikido->resolutionPlan($ids);
        } catch (AikidoException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            return $this->failure('E_INVALID_INPUT', $e->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        $this->success('aikido_plan', $plan);

        return self::SUCCESS;
    }
}
