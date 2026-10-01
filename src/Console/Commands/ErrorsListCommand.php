<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\BoogleService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class ErrorsListCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:errors-list
                            {--new : Only the errors nobody decided about, and the ones that came back after a fix}
                            {--kind= : One kind: error (thrown by the code) or outage (found by the uptime monitor)}
                            {--limit= : List at most this many, the ones thrown the most first}
                            {--report : Write the errors to {paths.support}/errors.md}';

    /**
     * The name it had when Boogle was the only tracker.
     *
     * @var list<string>
     */
    protected $aliases = ['larapilot:boogle-errors'];

    protected $description = 'Download the open errors of production from the tracker of the project, one entry for each bug, with what was decided about it';

    public function handle(BoogleService $boogle, ConfigService $config): int
    {
        if (! $config->errorsEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Production errors are off for this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --errors=YES --errors-provider=boogle (or sentry, bugsnag, flare, datadog, rollbar, honeybadger, cloudwatch)'
            );
        }

        try {
            // One download: the report is about every error, the list
            // about the ones that were asked for.
            $all = $boogle->errors();
            $errors = $boogle->errors([
                'new' => (bool) $this->option('new'),
                'kind' => $this->option('kind'),
                'limit' => is_numeric($this->option('limit')) ? (int) $this->option('limit') : null,
            ], false);
        } catch (BoogleException $e) {
            return $this->failure('E_CONNECTOR', $e->getMessage(), $this->exitForCode('E_CONNECTOR'), $e->hint());
        } catch (InvalidArgumentException $e) {
            return $this->failure('E_INVALID_INPUT', $e->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        $errors['report'] = $this->option('report') ? $boogle->writeReport($all)['relative'] : null;

        // What is read here is read by an agent: the list of every time an
        // error was thrown, and the days of the chart, are left to the page.
        unset($errors['days']);

        foreach ($errors['errors'] as $index => $error) {
            unset($errors['errors'][$index]['occurrences']);

            $errors['errors'][$index]['codes'] = array_slice($error['codes'], 0, 5);
        }

        return $this->success('errors_list', $errors);
    }
}
