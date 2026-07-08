<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;
use Symfony\Component\Yaml\Yaml;

class SpecRequestChangesCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-request-changes
                            {code : Spec code}
                            {--file= : Feedback YAML or JSON file}';

    protected $description = 'Send a spec in REVIEW back to TODO with rework feedback';

    public function handle(SpecService $specs, ConfigService $config, string $code): int
    {
        $spec = $specs->find($code);

        if ($spec === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        $file = $this->option('file');

        if ($file === null || ! is_file($file)) {
            return $this->failure('E_INVALID_INPUT', 'A valid --file path is required.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $raw = file_get_contents($file) ?: '';

        $feedback = match ($extension) {
            'json' => json_decode($raw, true),
            default => Yaml::parse($raw),
        };

        if (! is_array($feedback)) {
            return $this->failure('E_INVALID_INPUT', 'Invalid feedback payload.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $body = (string) ($spec['body'] ?? '');
        $feedbackMarkdown = (string) ($feedback['markdown'] ?? $feedback['body'] ?? '');

        if ($feedbackMarkdown !== '') {
            $body .= "\n\n## Rework Feedback\n\n".$feedbackMarkdown;
        }

        $specs->update($code, [
            'body' => $body,
            'rework' => true,
            'status' => $config->status('todo'),
        ]);

        return $this->success('request_changes_result', [
            'code' => $code,
            'status' => $config->status('todo'),
            'rework' => true,
        ]);
    }
}
