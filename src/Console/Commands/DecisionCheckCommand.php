<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\DecisionService;
use Larapilot\Support\LarapilotCommand;

class DecisionCheckCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:decision-check
                            {--topic= : Topic to look up (e.g. "background color")}
                            {--value= : Candidate new value — conflicts are earlier choices that differ from it}
                            {--limit=0 : Cap the returned history length}';

    protected $description = 'Read-only: show prior decisions for a topic and flag regressions against a candidate value';

    public function handle(DecisionService $decisions): int
    {
        $topic = trim((string) ($this->option('topic') ?? ''));

        if ($topic === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide --topic.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $value = (string) ($this->option('value') ?? '');
        $limit = max(0, (int) $this->option('limit'));

        $history = $decisions->history($topic);

        if ($limit > 0 && count($history) > $limit) {
            $history = array_slice($history, 0, $limit);
        }

        $conflicts = $value !== '' ? $decisions->conflicts($topic, $value) : [];

        return $this->success('decision_check', [
            'topic' => $decisions->normalizeTopic($topic),
            'value' => $value !== '' ? $value : null,
            'history' => $history,
            'conflicts' => $conflicts,
            'has_regression' => $conflicts !== [],
            'path' => $decisions->path(),
        ]);
    }
}
