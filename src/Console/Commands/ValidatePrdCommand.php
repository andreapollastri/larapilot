<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\PrdService;
use Larapilot\Services\ValidationService;
use Larapilot\Support\LarapilotCommand;

class ValidatePrdCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:validate-prd
                            {--file= : Optional PRD file to validate instead of the configured path}';

    protected $description = 'Validate PRD structure';

    public function handle(PrdService $prd, ValidationService $validation): int
    {
        $content = null;

        if ($file = $this->option('file')) {
            if (! is_file($file)) {
                return $this->failure('E_NOT_FOUND', "PRD file not found: {$file}", $this->exitForCode('E_NOT_FOUND'));
            }

            $content = file_get_contents($file) ?: '';
        } elseif (! $prd->exists()) {
            return $this->failure('E_PRECONDITION', 'PRD file does not exist.', $this->exitForCode('E_PRECONDITION'), 'Run larapilot-inception or larapilot:prd-write first.');
        }

        return $this->validationResult($validation->validatePrd($content));
    }
}
