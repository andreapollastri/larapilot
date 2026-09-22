<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\DecisionService;
use Larapilot\Services\MockupService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\SpecCode;

class MockupChooseStyleCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:mockup-choose-style
                            {spec : Spec code (e.g. US-001)}
                            {--style= : Style folder slug under mockups/{spec}/styles/}
                            {--no-decision-log : Skip decision-log even when the journal is on}';

    protected $description = 'Record which mockup style version to implement for a spec';

    public function handle(MockupService $mockups, DecisionService $decisions, ConfigService $config): int
    {
        $code = strtoupper(trim($this->argument('spec') ?? ''));

        if (! SpecCode::isValid($code)) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Invalid spec code.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $style = strtolower(trim((string) $this->option('style')));

        if ($style === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Provide --style= with the folder slug under mockups/'.$code.'/styles/.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        try {
            $manifest = $mockups->chooseStyle($code, $style);
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $label = $style;

        foreach ($manifest['styles'] as $row) {
            if (($row['id'] ?? '') === $style) {
                $label = (string) ($row['label'] ?? $style);
                break;
            }
        }

        $decision = null;

        if (! (bool) $this->option('no-decision-log') && $config->decisionLogEnabled()) {
            $decision = $decisions->log([
                'topic' => 'mockup style',
                'value' => $style,
                'label' => 'Mockup style for '.$code,
                'rationale' => $label,
                'source' => 'askquestion',
                'skill' => 'larapilot-design',
                'spec' => $code,
            ]);
        }

        return $this->success('mockup_style_chosen', [
            'spec' => $code,
            'style' => $style,
            'label' => $label,
            'manifest' => $manifest,
            'decision' => $decision,
        ]);
    }
}
