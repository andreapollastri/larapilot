<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\ArtifactSections;
use Larapilot\Support\PrdIds;
use Larapilot\Support\SpecCode;

class ValidationService
{
    /**
     * @return array{ok: bool, findings: array<int, array<string, string>>}
     */
    public function validatePrd(?string $content = null): array
    {
        $content ??= app(PrdService::class)->read() ?? '';
        $findings = [];
        $missing = [];

        foreach (ArtifactSections::prd() as $name => $aliases) {
            if (! $this->hasSection($content, $aliases)) {
                $missing[] = $name;
            }
        }

        if ($missing !== [] && ! $this->hasMinimumLevel2Headings($content, ArtifactSections::minimumPrdHeadings())) {
            foreach ($missing as $name) {
                $findings[] = [
                    'code' => 'PRD_MISSING_SECTION',
                    'severity' => 'error',
                    'path' => $name,
                    'message' => "PRD is missing required section: {$name}.",
                    'hint' => 'Add a level-2 heading for this section in the artifact language, or provide at least '.ArtifactSections::minimumPrdHeadings().' ## headings with substantive content.',
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
        } else {
            foreach (ArtifactSections::prdRecommended() as $name => $aliases) {
                if (! $this->hasSection($content, $aliases)) {
                    $findings[] = $this->finding(
                        'PRD_RECOMMENDED_SECTION',
                        'warning',
                        $name,
                        "PRD has no {$name} section.",
                        'A fresh inception writes it (Domain Model & User Journeys, Requirement Quality in runtime-discovery.md); older PRDs stay valid without it.'
                    );
                }
            }

            foreach ($this->frCodesWithoutMoscow($content) as $code) {
                $findings[] = $this->finding(
                    'PRD_FR_MISSING_MOSCOW',
                    'warning',
                    $code,
                    "{$code} has no **MoSCoW:** line.",
                    'Add **MoSCoW:** Must | Should | Could | Won\'t under the FR heading (MoSCoW Prioritization in runtime-discovery.md).'
                );
            }

            foreach (PrdIds::duplicates($content) as $id) {
                $findings[] = $this->finding(
                    'PRD_DUPLICATE_ID',
                    'warning',
                    $id,
                    "{$id} is defined more than once.",
                    'Ids are permanent and unique: keep one definition and give the other a new id (PRD Revision in runtime-ops.md).'
                );
            }

            foreach (PrdIds::dangling($content) as $id) {
                $findings[] = $this->finding(
                    'PRD_DANGLING_REFERENCE',
                    'warning',
                    $id,
                    "{$id} is cited but never defined.",
                    'Define it, fix the citation, or retire the id with its heading kept (PRD Revision in runtime-ops.md).'
                );
            }
        }

        return [
            'ok' => ! $this->hasErrors($findings),
            'findings' => $findings,
        ];
    }

    /**
     * FR headings (`### FR-XXX`) whose block, up to the next FR or level-2 heading,
     * carries no `**MoSCoW:**` label.
     *
     * @return list<string>
     */
    protected function frCodesWithoutMoscow(string $content): array
    {
        $parts = preg_split('/^###\s+(FR-\d+)\b[^\n]*$/mi', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || count($parts) < 3) {
            return [];
        }

        $missing = [];

        for ($i = 1; $i < count($parts); $i += 2) {
            $block = preg_split('/^##\s/m', (string) ($parts[$i + 1] ?? ''))[0] ?? '';

            if (preg_match('/\*\*MoSCoW:\*\*/i', $block) !== 1) {
                $missing[] = strtoupper($parts[$i]);
            }
        }

        return $missing;
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

            $code = (string) ($spec['code'] ?? '');

            if ($code !== '' && ! SpecCode::isValid($code)) {
                $findings[] = $this->finding('SPEC_INVALID_CODE', 'error', "{$prefix}.code", "Spec code contains invalid characters: {$code}.", 'Use letters, digits, dots, dashes, and underscores (e.g. US-001).');
            }

            $body = (string) ($spec['body'] ?? '');
            $missingSections = [];

            foreach (ArtifactSections::spec() as $section => $names) {
                if (! $this->hasSection($body, $names)) {
                    $missingSections[] = $section;
                }
            }

            if ($missingSections !== [] && ! $this->hasMinimumMarkedSections($body, ArtifactSections::minimumSpecSections())) {
                foreach ($missingSections as $section) {
                    $findings[] = $this->finding('SPEC_MISSING_SECTION', 'error', "{$prefix}.body", "Spec body is missing section: {$section}.", "Include a marked heading for {$section} (## or **heading**) in the artifact language.");
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

                if (! $this->hasSection($body, ArtifactSections::taskDescription()) && ! $this->hasMinimumLevel2Headings($body, 1)) {
                    $findings[] = $this->finding('TASK_MISSING_SECTION', 'error', "tasks[{$index}].body", "Task {$id} body must include a Description section.", 'Use a level-2 heading for the task description in the artifact language.');
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
        } elseif (! SpecCode::isValid($code)) {
            $findings[] = $this->finding('PLAN_INVALID_CODE', 'error', 'code', "Spec code contains invalid characters: {$code}.", 'Use letters, digits, dots, dashes, and underscores (e.g. US-001).');
        }

        return [
            'ok' => ! $this->hasErrors($findings),
            'findings' => $findings,
        ];
    }

    /**
     * A section counts only when marked up as a heading (## Name) or
     * bold label (**Name**), matching the spec template — a passing
     * mention in prose is not enough.
     *
     * @param  list<string>  $names
     */
    protected function hasSection(string $body, array $names): bool
    {
        foreach ($names as $name) {
            $quoted = preg_quote($name, '/');

            if (preg_match('/(^|\n)\s*(#{1,6}\s+[^\n]*'.$quoted.'|\*\*'.$quoted.'\*\*)/i', $body) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function hasMinimumLevel2Headings(string $content, int $minimum): bool
    {
        return preg_match_all('/^##\s+\S/m', $content) >= $minimum;
    }

    protected function hasMinimumMarkedSections(string $body, int $minimum): bool
    {
        return preg_match_all('/(^|\n)\s*(#{1,6}\s+\S|\*\*[^*\n]+\*\*)/m', $body) >= $minimum;
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
