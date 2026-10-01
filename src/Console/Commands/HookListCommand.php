<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\HookService;
use Larapilot\Support\Envelope;
use Larapilot\Support\LarapilotCommand;

class HookListCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:hook-list
                            {--event= : Only this event (task.done, spec.review, …)}';

    protected $description = 'List and check the workflow hooks of .larapilot/hooks.yaml';

    public function handle(HookService $hooks): int
    {
        $event = $this->option('event');
        $event = is_string($event) && trim($event) !== '' ? trim($event) : null;

        if ($event !== null && ! array_key_exists($event, HookService::EVENTS)) {
            return $this->failure(
                'E_INVALID_INPUT',
                "Unknown event {$event}.",
                $this->exitForCode('E_INVALID_INPUT'),
                'Events: '.implode(', ', array_keys(HookService::EVENTS)).'.'
            );
        }

        $listing = $hooks->listing($event);

        // A file with errors answers like a failed validation: the listing,
        // and an exit code a CI step can stop on.
        $this->line(Envelope::success('hook_list', $listing));

        return $listing['ok'] ? self::SUCCESS : $this->exitForCode('E_INVALID_INPUT');
    }
}
