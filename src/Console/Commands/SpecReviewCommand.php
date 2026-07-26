<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\PlanService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecReviewCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-review
                            {code : Spec code}
                            {--note= : Optional review note markdown}
                            {--commit-type=chore : Conventional commit type}
                            {--commit-summary= : Optional commit summary}
                            {--force : Move to REVIEW even with incomplete plan tasks}';

    protected $description = 'Move a spec to REVIEW after implementation';

    public function handle(SpecService $specs, ConfigService $config, PlanService $plans): int
    {
        $code = (string) $this->argument('code');
        $spec = $specs->find($code);

        if ($spec === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        if (($guard = $this->guardStatus($spec, [$config->status('in_progress')], 'review')) !== null) {
            return $guard;
        }

        if (! (bool) $this->option('force')) {
            $progress = $plans->taskProgress($code);

            if ($progress['total'] > 0 && $progress['done'] < $progress['total']) {
                return $this->failure(
                    'E_PRECONDITION',
                    "Spec {$code} has incomplete plan tasks ({$progress['done']}/{$progress['total']} done).",
                    $this->exitForCode('E_PRECONDITION'),
                    'Finish the remaining tasks via larapilot:task-done, or pass --force to override.'
                );
            }
        }

        $specs->setStatus($code, $config->status('review'));

        return $this->success('review_result', [
            'code' => $code,
            'status' => $config->status('review'),
            'note' => $this->option('note'),
            'commit' => [
                'type' => $this->option('commit-type'),
                'summary' => $this->option('commit-summary') ?: ($spec['title'] ?? $code),
            ],
        ]);
    }
}
