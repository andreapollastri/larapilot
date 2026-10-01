<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\PrdService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\PrdIds;
use Larapilot\Support\PrdReader;

class PrdShowCommand extends LarapilotCommand
{
    /**
     * Characters of Markdown one answer may carry. Past this an editor cuts
     * the output of a command, and a cut PRD reads as a complete one.
     */
    protected const LIMIT = 24000;

    protected $signature = 'larapilot:prd-show
                            {--ids= : Comma-separated PRD ids to read (FR-004,J-001,NFR-002,Q-001)}
                            {--section= : Comma-separated section titles to read (MVP Scope,Technical Architecture)}';

    protected $description = 'Read the PRD by the piece: its outline, the blocks of some ids, or some sections';

    public function handle(ConfigService $config, PrdService $prd): int
    {
        $content = $prd->read();

        if ($content === null) {
            return $this->failure(
                'E_PRECONDITION',
                'PRD file does not exist.',
                $this->exitForCode('E_PRECONDITION'),
                'Run larapilot-inception or larapilot-adopt first.'
            );
        }

        $ids = $this->listed('ids');
        $titles = $this->listed('section');
        $path = $config->relativePath($prd->path());

        if ($ids === [] && $titles === []) {
            return $this->success('prd_outline', [
                'path' => $path,
                'tokens' => (int) ceil(mb_strlen($content) / 4),
            ] + PrdReader::outline($content));
        }

        $invalid = array_values(array_filter($ids, static fn (string $id): bool => ! PrdIds::isValid($id)));

        if ($invalid !== []) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Invalid PRD id: '.implode(', ', $invalid).'.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Use FR-, J-, NFR-, or Q- ids as written in the PRD, e.g. --ids=FR-004,J-001.'
            );
        }

        $blocks = PrdReader::blocks($content, $ids);
        $sections = PrdReader::sections($content, $titles);
        $length = 0;

        foreach (array_merge($blocks['blocks'], $sections['sections']) as $piece) {
            $length += mb_strlen($piece['markdown']);
        }

        if ($length > self::LIMIT) {
            return $this->failure(
                'E_INVALID_INPUT',
                "That is {$length} characters of the PRD, more than one answer carries whole.",
                $this->exitForCode('E_INVALID_INPUT'),
                "Ask for fewer ids or sections, or read {$path} with the editor file-read tool."
            );
        }

        return $this->success('prd_slice', [
            'path' => $path,
            'blocks' => $blocks['blocks'],
            'sections' => $sections['sections'],
            'unknown' => array_merge($blocks['unknown'], $sections['unknown']),
        ]);
    }

    /**
     * @return list<string>
     */
    protected function listed(string $option): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $this->option($option))
        ), static fn (string $value): bool => $value !== ''));
    }
}
