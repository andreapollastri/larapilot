<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;
use Symfony\Component\Yaml\Yaml;

class SpecRequestChangesCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-request-changes
                            {code : Spec code}
                            {--file= : Feedback YAML or JSON file}
                            {--include-feedback : Append blocking internal-feedback comments marked [blocks-merge]}';

    protected $description = 'Send a spec in REVIEW back to TODO with rework feedback';

    public function handle(
        SpecService $specs,
        ConfigService $config,
        InternalFeedbackService $feedback,
    ): int {
        $code = (string) $this->argument('code');
        $spec = $specs->find($code);

        if ($spec === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        if (($guard = $this->guardStatus($spec, [$config->status('review')], 'request changes on')) !== null) {
            return $guard;
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
            return $this->failure('E_INVALID_INPUT', 'Invalid feedback payload.', $this->exitForCode('E_INVALID_INPUT'));
        }

        $feedbackMarkdown = trim((string) ($payload['markdown'] ?? $payload['body'] ?? ''));

        if ($this->option('include-feedback')) {
            $fromFeedback = $feedback->blockingMarkdown($code);

            if ($fromFeedback !== '') {
                $feedbackMarkdown = trim($feedbackMarkdown) === ''
                    ? $fromFeedback
                    : trim($feedbackMarkdown)."\n\n".$fromFeedback;
            }
        }

        if ($feedbackMarkdown === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Feedback markdown is empty.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Provide markdown/body in the payload or use --include-feedback with blocking comments.'
            );
        }

        $specs->requestChanges($code, $feedbackMarkdown);

        return $this->success('request_changes_result', [
            'code' => $code,
            'status' => $config->status('todo'),
            'rework' => true,
            'included_feedback' => (bool) $this->option('include-feedback'),
        ]);
    }
}
