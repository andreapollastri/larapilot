<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ValidationService;
use Larapilot\Support\LarapilotCommand;
use Larapilot\Support\PayloadFile;

class ValidatePlanCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:validate-plan
                            {code : Spec code}
                            {--file= : Plan payload file}';

    protected $description = 'Validate a generated plan payload without changing spec status';

    public function handle(ValidationService $validation): int
    {
        $code = (string) $this->argument('code');
        $file = $this->option('file');

        if (! is_string($file) || ! is_file($file)) {
            return $this->failure('E_INVALID_INPUT', 'A valid --file path is required.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $payload = PayloadFile::parse($file);

        if ($payload === null) {
            return $this->failure('E_INVALID_INPUT', 'Invalid plan payload.', $this->exitForCode('E_INVALID_INPUT'));
        }

        return $this->validationResult($validation->validatePlanPayload($code, $payload));
    }
}
