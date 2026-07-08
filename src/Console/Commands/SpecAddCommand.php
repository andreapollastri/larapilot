<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SpecService;
use Larapilot\Services\ValidationService;
use Larapilot\Support\LarapilotCommand;
use Symfony\Component\Yaml\Yaml;

class SpecAddCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-add
                            {--file= : YAML or JSON file with specs payload}';

    protected $description = 'Create or extend the backlog with specs';

    public function handle(SpecService $specs, ValidationService $validation): int
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

        $result = $validation->validateSpecPayload($payload);

        if (! $result['ok']) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Specs payload failed validation.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Fix the findings and retry.',
                ['findings' => $result['findings']]
            );
        }

        $specs->add($payload['specs']);

        return $this->success('spec_add_result', [
            'added' => count($payload['specs']),
            'summary' => $specs->list()['summary'],
        ]);
    }
}
