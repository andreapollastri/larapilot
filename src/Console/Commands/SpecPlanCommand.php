<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\PlanService;
use Larapilot\Services\SpecService;
use Larapilot\Services\ValidationService;
use Larapilot\Support\LarapilotCommand;
use Symfony\Component\Yaml\Yaml;

class SpecPlanCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-plan
                            {code : Spec code}
                            {--file= : Plan payload YAML or JSON file}';

    protected $description = 'Save implementation plan and move spec to PLANNED';

    public function handle(PlanService $plans, SpecService $specs, ValidationService $validation, string $code): int
    {
        if ($specs->find($code) === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

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
            return $this->failure('E_INVALID_INPUT', 'Invalid plan payload.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $result = $validation->validatePlanPayload($code, $payload);

        if (! $result['ok']) {
            return $this->success('validation_result', $result);
        }

        $plans->save($code, $payload);

        return $this->success('plan_result', [
            'code' => $code,
            'path' => $plans->path($code),
            'task_count' => count($payload['tasks'] ?? []),
        ]);
    }
}
