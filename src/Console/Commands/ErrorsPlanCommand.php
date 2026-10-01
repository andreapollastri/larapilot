<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\BoogleService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class ErrorsPlanCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:errors-plan
                            {--codes= : Comma-separated error codes (#BUG12) or keys confirmed for triage}';

    /**
     * The name it had when Boogle was the only tracker.
     *
     * @var list<string>
     */
    protected $aliases = ['larapilot:boogle-plan'];

    protected $description = 'Group the errors of production the user confirmed by domain and place in the code, one triage handoff for each group';

    public function handle(BoogleService $errors, ConfigService $config): int
    {
        if (! $config->errorsEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Production errors are off for this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --errors=YES --errors-provider=boogle (or sentry, bugsnag, flare, datadog, rollbar, honeybadger, cloudwatch)'
            );
        }

        $raw = trim((string) $this->option('codes'));

        if ($raw === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Name the errors to plan.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Example: php artisan larapilot:errors-plan --codes=BUG12,BUG21'
            );
        }

        $codes = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $part): bool => $part !== ''));

        try {
            $plan = $errors->resolutionPlan($codes);
        } catch (BoogleException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            $code = str_contains($e->getMessage(), 'holds no open error') ? 'E_NOT_FOUND' : 'E_INVALID_INPUT';

            return $this->failure($code, $e->getMessage(), $this->exitForCode($code));
        }

        $this->success('errors_plan', $plan);

        return self::SUCCESS;
    }
}
