<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\FrontendService;
use Larapilot\Support\LarapilotCommand;

class FrontendRulesCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:frontend-rules
                            {--file=* : File about to be written, relative to the frontend root (repeat, or comma-separated)}
                            {--project=* : Target project instead of the configured ones, when no --file is given}
                            {--path= : Read this absolute path instead of the configured frontend repo}';

    protected $description = 'List the agent rules of the frontend repository (AGENTS.md, CLAUDE.md, Cursor, Copilot, …) that govern some files or the target projects';

    public function handle(FrontendService $frontend): int
    {
        $path = $this->option('path');
        $files = $this->values('file');
        $projects = $this->values('project');

        $rules = $frontend->rules(
            $files,
            $projects === [] ? null : $projects,
            is_string($path) && trim($path) !== '' ? trim($path) : null,
        );

        if (($rules['ok'] ?? false) !== true) {
            return $this->failure(
                $files !== [] && isset($rules['path']) ? 'E_INVALID_INPUT' : 'E_PRECONDITION',
                (string) ($rules['error'] ?? 'Frontend repository not readable.'),
                $files !== [] && isset($rules['path']) ? $this->exitForCode('E_INVALID_INPUT') : $this->exitForCode('E_PRECONDITION'),
                is_array($rules['errors'] ?? null) ? implode(' ', $rules['errors']) : null
            );
        }

        return $this->success('frontend-rules', $rules);
    }

    /**
     * @return list<string>
     */
    protected function values(string $option): array
    {
        $values = [];

        foreach ((array) $this->option($option) as $value) {
            foreach (explode(',', (string) $value) as $part) {
                if (trim($part) !== '') {
                    $values[] = trim($part);
                }
            }
        }

        return array_values(array_unique($values));
    }
}
