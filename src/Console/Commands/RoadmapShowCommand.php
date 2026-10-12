<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\RoadmapService;
use Larapilot\Support\LarapilotCommand;

/**
 * The public roadmap as the page shows it, with the state of the public
 * link — for the skills that keep it current when a version changes.
 */
class RoadmapShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:roadmap-show';

    protected $description = 'Show the public product roadmap derived from the releases';

    public function handle(RoadmapService $roadmap): int
    {
        $curated = $roadmap->read();

        return $this->success('roadmap', $roadmap->build() + [
            'public' => [
                'enabled' => $roadmap->enabled(),
                'url' => $roadmap->publicUrl(),
                'has_token' => $roadmap->token() !== null,
            ],
            'curated' => [
                'path' => $roadmap->path(),
                'intro' => $curated['intro'],
                'versions' => $curated['versions'],
            ],
            'left_out' => RoadmapService::LEFT_OUT,
        ]);
    }
}
