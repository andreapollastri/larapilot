<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\FrontendBriefService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SpecCode;

class FrontendBriefCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:frontend-brief
                            {code : Spec code}
                            {--task=* : Only these frontend tasks (repeat, or comma-separated)}
                            {--stdout : Return the brief in the envelope instead of writing it}';

    protected $description = 'Write the brief the frontend team builds a spec from (frontend.mode handoff): story, frontend tasks, API contract, mockups';

    public function handle(FrontendBriefService $briefs): int
    {
        $code = (string) $this->argument('code');

        if (! SpecCode::isValid($code)) {
            return $this->failure('E_INVALID_INPUT', "Invalid spec code: {$code}.", $this->exitForCode('E_INVALID_INPUT'));
        }

        $tasks = [];

        foreach ((array) $this->option('task') as $value) {
            foreach (explode(',', (string) $value) as $task) {
                if (trim($task) !== '') {
                    $tasks[] = strtoupper(trim($task));
                }
            }
        }

        try {
            $brief = $briefs->build($code, array_values(array_unique($tasks)), ! (bool) $this->option('stdout'));
        } catch (\RuntimeException $exception) {
            $notFound = str_contains($exception->getMessage(), 'not found');

            return $this->failure(
                $notFound ? 'E_NOT_FOUND' : 'E_PRECONDITION',
                $exception->getMessage(),
                $this->exitForCode($notFound ? 'E_NOT_FOUND' : 'E_PRECONDITION')
            );
        }

        return $this->success('frontend-brief', array_filter($brief, static fn (mixed $value): bool => $value !== null));
    }
}
