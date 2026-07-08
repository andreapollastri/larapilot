<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ValidationService;
use Larapilot\Support\LarapilotCommand;
use Symfony\Component\Yaml\Yaml;

class ValidateSpecCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:validate-spec
                            {--file= : Specs payload file}';

    protected $description = 'Validate a generated specs payload without persisting it';

    public function handle(ValidationService $validation): int
    {
        $file = $this->option('file');

        if ($file === null || ! is_file($file)) {
            return $this->failure('E_INVALID_INPUT', 'A valid --file path is required.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $raw = file_get_contents($file) ?: '';

        $payload = match ($extension) {
            'json' => json_decode($raw, true),
            default => Yaml::parse($raw),
        };

        if (! is_array($payload)) {
            return $this->failure('E_INVALID_INPUT', 'Invalid specs payload.', $this->exitForCode('E_INVALID_INPUT'));
        }

        return $this->validationResult($validation->validateSpecPayload($payload));
    }
}
