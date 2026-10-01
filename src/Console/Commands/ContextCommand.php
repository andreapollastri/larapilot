<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ContextService;
use Larapilot\Support\LarapilotCommand;

class ContextCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:context
                            {skill : Skill being activated, with or without the larapilot- prefix (implement, larapilot-plan, a custom skill)}
                            {--session= : Token an earlier context call of this conversation returned}
                            {--fresh : The conversation was compacted: forget what the session loaded and read everything again}
                            {--with= : Extra runtime packs, comma-separated (delivery-1, dev-docs, or a whole group such as delivery)}';

    protected $description = 'Settings, paths, and the runtime files a skill reads at activation, minus what the session already loaded';

    public function handle(ContextService $context): int
    {
        $with = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('with'))
        ), static fn (string $pack): bool => $pack !== ''));

        $session = $this->option('session');

        try {
            $data = $context->resolve(
                (string) $this->argument('skill'),
                is_string($session) ? trim($session) : null,
                (bool) $this->option('fresh'),
                $with
            );
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT'),
                'Name the skill as its slash command does, and packs as `.larapilot/shared-runtime.md` lists them.'
            );
        }

        return $this->success('context', $data);
    }
}
