<?php

declare(strict_types=1);

namespace Larapilot\Services;

class ValidationService
{
    /**
     * @return array{ok: bool, findings: array<int, array<string, string>>}
     */
    public function validatePrd(?string $content = null): array
    {
        $content ??= app(PrdService::class)->read() ?? '';
        $findings = [];

        $requiredSections = [
            'Elevator Pitch' => '## Elevator Pitch',
            'Vision' => '## Vision',
            'User Personas' => '## User Personas',
            'Functional Requirements' => '## Functional Requirements',
            'MVP Scope' => '## MVP Scope',
            'Technical Architecture' => '## Technical Architecture',
        ];

        foreach ($requiredSections as $name => $marker) {
            if (! str_contains($content, $marker)) {
                $findings[] = [
                    'code' => 'PRD_MISSING_SECTION',
                    'severity' => 'error',
                    'path' => $name,
                    'message' => "PRD is missing required section: {$name}.",
                    'hint' => "Add a '{$marker}' section to the PRD.",
                ];
            }
        }

        if (trim($content) === '') {
            $findings[] = [
                'code' => 'PRD_EMPTY',
                'severity' => 'error',
                'path' => 'prd',
                'message' => 'PRD content is empty.',
                'hint' => 'Write product discovery content before validating.',
            ];
        }

        return [
            'ok' => ! $this->hasErrors($findings),
            'findings' => $findings,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, findings: array<int, array<string, string>>}
     */
    public function validateSpecPayload(array $payload): array
    {
        $findings = [];
        $specs = $payload['specs'] ?? null;

        if (! is_array($specs) || $specs === []) {
            $findings[] = $this->finding('SPEC_EMPTY', 'error', 'specs', 'At least one spec is required.', 'Provide a specs array with one or more items.');

            return ['ok' => false, 'findings' => $findings];
        }

        foreach ($specs as $index => $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $prefix = "specs[{$index}]";

            foreach (['code', 'title', 'body'] as $field) {
                if (empty($spec[$field])) {
                    $findings[] = $this->finding('SPEC_MISSING_FIELD', 'error', "{$prefix}.{$field}", "Spec is missing required field: {$field}.", "Set {$field} on every spec.");
                }
            }

            $body = (string) ($spec['body'] ?? '');

            foreach (['User Story', 'Demonstrates', 'Acceptance Criteria'] as $section) {
                if (! str_contains($body, $section)) {
                    $findings[] = $this->finding('SPEC_MISSING_SECTION', 'error', "{$prefix}.body", "Spec body is missing section: {$section}.", "Include a '{$section}' section in the spec body.");
                }
            }
        }

        return [
            'ok' => ! $this->hasErrors($findings),
            'findings' => $findings,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, findings: array<int, array<string, string>>}
     */
    public function validatePlanPayload(string $code, array $payload): array
    {
        $findings = [];

        if (trim((string) ($payload['plan_body'] ?? '')) === '') {
            $findings[] = $this->finding('PLAN_EMPTY_BODY', 'error', 'plan_body', 'Plan body is required.', 'Provide technical solution and test strategy in plan_body.');
        }

        $tasks = $payload['tasks'] ?? null;

        if (! is_array($tasks) || $tasks === []) {
            $findings[] = $this->finding('PLAN_NO_TASKS', 'error', 'tasks', 'At least one task is required.', 'Add implementation and test tasks.');
        } else {
            $ids = [];

            foreach ($tasks as $index => $task) {
                if (! is_array($task)) {
                    continue;
                }

                $id = (string) ($task['id'] ?? '');
                $ids[] = $id;

                foreach (['id', 'title', 'body', 'type', 'status'] as $field) {
                    if (empty($task[$field])) {
                        $findings[] = $this->finding('TASK_MISSING_FIELD', 'error', "tasks[{$index}].{$field}", "Task {$id} is missing {$field}.", "Set {$field} on every task.");
                    }
                }

                $body = (string) ($task['body'] ?? '');

                if (! str_contains($body, '## Description') && ! str_contains($body, '## Descrizione')) {
                    $findings[] = $this->finding('TASK_MISSING_SECTION', 'error', "tasks[{$index}].body", "Task {$id} body must include a Description section.", 'Use ## Description or ## Descrizione.');
                }
            }

            foreach ($tasks as $index => $task) {
                if (! is_array($task)) {
                    continue;
                }

                $dependencies = $task['dependencies'] ?? [];

                if (! is_array($dependencies)) {
                    continue;
                }

                foreach ($dependencies as $dependency) {
                    if (! in_array($dependency, $ids, true)) {
                        $findings[] = $this->finding('TASK_UNKNOWN_DEPENDENCY', 'error', "tasks[{$index}].dependencies", "Task references unknown dependency: {$dependency}.", 'Dependencies must reference task ids in the same payload.');
                    }
                }
            }
        }

        if ($code === '') {
            $findings[] = $this->finding('PLAN_MISSING_CODE', 'error', 'code', 'Spec code is required for plan validation.', 'Pass a valid US-XXX code.');
        }

        return [
            'ok' => ! $this->hasErrors($findings),
            'findings' => $findings,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $findings
     */
    protected function hasErrors(array $findings): bool
    {
        foreach ($findings as $finding) {
            if (($finding['severity'] ?? '') === 'error') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function finding(string $code, string $severity, string $path, string $message, string $hint): array
    {
        return compact('code', 'severity', 'path', 'message', 'hint');
    }
}
