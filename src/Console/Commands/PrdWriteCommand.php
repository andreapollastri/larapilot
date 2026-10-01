<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\PrdService;
use Larapilot\Support\LarapilotCommand;

class PrdWriteCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:prd-write
                            {--file= : Path to PRD markdown file}
                            {--content= : PRD markdown content}
                            {--skill-hooks-done= : Skill hooks of the before phase already run, comma-separated (settings.hooks)}';

    protected $description = 'Persist PRD markdown to the configured path';

    protected bool $refreshesEconomics = true;

    public function handle(PrdService $prd): int
    {
        $content = $this->option('content');

        if ($file = $this->option('file')) {
            if (! is_file($file)) {
                return $this->failure('E_NOT_FOUND', "PRD file not found: {$file}", $this->exitForCode('E_NOT_FOUND'));
            }

            $content = file_get_contents($file) ?: '';
        }

        if ($content === null || $content === '') {
            $content = stream_get_contents(STDIN) ?: '';
        }

        if (trim($content) === '') {
            return $this->failure('E_INVALID_INPUT', 'PRD content is empty.', $this->exitForCode('E_INVALID_INPUT'), 'Pass --file, --content, or pipe markdown via stdin.');
        }

        $hookContext = ['path' => $prd->path(), 'bytes' => strlen($content)];

        if (($blocked = $this->beforeHooks('prd.written', $hookContext)) !== null) {
            return $blocked;
        }

        $prd->write($content);
        $this->afterHooks('prd.written', $hookContext);

        return $this->success('write_result', [
            'path' => $prd->path(),
            'bytes' => strlen($content),
        ]);
    }
}
