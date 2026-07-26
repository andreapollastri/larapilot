<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ValidationService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\PayloadFile;

class ValidateSpecCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:validate-spec
                            {--file= : Specs payload file}';

    protected $description = 'Validate a generated specs payload without persisting it';

    public function handle(ValidationService $validation): int
    {
        $file = $this->option('file');

        if (! is_string($file) || ! is_file($file)) {
            return $this->failure('E_INVALID_INPUT', 'A valid --file path is required.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $payload = PayloadFile::parse($file);

        if ($payload === null) {
            return $this->failure('E_INVALID_INPUT', 'Invalid specs payload.', $this->exitForCode('E_INVALID_INPUT'));
        }

        return $this->validationResult($validation->validateSpecPayload($payload));
    }
}
