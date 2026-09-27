<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\Boogle\BoogleException;
use Larapilot\Services\BoogleService;
use Larapilot\Services\ConfigService;
use Larapilot\Support\LarapilotCommand;

class BoogleErrorsCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:boogle-errors
                            {--new : Only the errors nobody decided about, and the ones that came back after a fix}
                            {--kind= : One kind: error (thrown by the code) or outage (found by the uptime monitor)}
                            {--limit= : List at most this many, the ones thrown the most first}
                            {--report : Write the errors to {paths.support}/boogle.md}';

    protected $description = 'Download the open errors Boogle recorded for this application, one entry for each bug, with what was decided about it';

    public function handle(BoogleService $boogle, ConfigService $config): int
    {
        if (! $config->boogleEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Boogle is off for this project.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --boogle=YES'
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

        return $this->success('boogle_errors', $errors);
    }
}
