<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\DecisionService;
use Larapilot\Support\LarapilotCommand;

class DecisionLogCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:decision-log
                            {--topic= : What the decision is about (e.g. "background color")}
                            {--value= : The chosen value (e.g. "orange")}
                            {--label= : Human label for the topic (defaults to --topic)}
                            {--question= : The question that was asked, when applicable}
                            {--rationale= : Why this choice was made}
                            {--source=chat : chat|askquestion}
                            {--skill= : Larapilot skill / phase name}
                            {--spec= : Optional US-XXX}
                            {--supersedes= : Id of the prior decision this overrides}
                            {--ts= : ISO timestamp override}';

    protected $description = 'Append a user decision to the journal (.larapilot/decisions.yaml) and report any regression against earlier choices';

    public function handle(ConfigService $config, DecisionService $decisions): int
    {
        if (! $config->decisionLogEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'The decision journal is disabled (settings.decision_log = NO).',
                $this->exitForCode('E_PRECONDITION'),
                'Re-enable with: php artisan larapilot:settings-set --decision-log=YES'
            );
        }

        $topic = (string) ($this->option('topic') ?? '');
        $value = (string) ($this->option('value') ?? '');

        $conflicts = $decisions->conflicts($topic, $value);

        try {
            $entry = $decisions->log([
                'topic' => $topic,
                'value' => $value,
                'label' => $this->option('label'),
                'question' => $this->option('question'),
                'rationale' => $this->option('rationale'),
                'source' => $this->option('source'),
                'skill' => $this->option('skill'),
                'spec' => $this->option('spec'),
                'supersedes' => $this->option('supersedes'),
                'ts' => $this->option('ts'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        return $this->success('decision', [
            'decision' => $entry,
            'conflicts' => $conflicts,
            'has_regression' => $conflicts !== [] && $entry['supersedes'] === null,
            'path' => $decisions->path(),
        ]);
    }
}
