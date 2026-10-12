<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

/**
 * The sprint a spec is planned for, written on it in the backlog — or taken
 * off it, so the Board proposes one again.
 */
class SpecSprintCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-sprint
                            {code : Spec code, e.g. US-001}
                            {sprint : A positive sprint number, or none to clear it}';

    protected $description = 'Set or clear the sprint of a spec';

    protected bool $refreshesEconomics = true;

    public function handle(SpecService $specs, ConfigService $config): int
    {
        $code = (string) $this->argument('code');
        $raw = strtolower(trim((string) $this->argument('sprint')));

        if ($raw === 'none' || $raw === 'clear' || $raw === '-') {
            $sprint = null;
        } elseif (preg_match('/^\d+$/', $raw) === 1 && (int) $raw > 0) {
            $sprint = (int) $raw;
        } else {
            return $this->failure(
                'E_INVALID_INPUT',
                "Invalid sprint: {$raw}.",
                $this->exitForCode('E_INVALID_INPUT'),
                'Give a positive integer (1, 2, 3…) or none to clear the sprint.'
            );
        }

        if ($specs->find($code) === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        $specs->setSprint($code, $sprint);

        return $this->success('spec_sprint', [
            'code' => $code,
            'sprint' => $sprint,
            'proposed' => false,
            'sprints' => $config->sprintsEnabled() ? 'YES' : 'NO',
            'hint' => $config->sprintsEnabled()
                ? null
                : 'The sprint is kept in the backlog; the Board and the Plan group by sprint once settings.sprints is YES (php artisan larapilot:settings-set --sprints=YES).',
        ]);
    }
}
