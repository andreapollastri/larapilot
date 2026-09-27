<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Larapilot\Support\PrdIds;

/**
 * Traces PRD identifiers to the specs that cite them, so a PRD revision knows
 * which stories rest on the promise it is about to change.
 */
class PrdImpactService
{
    public function __construct(
        protected ConfigService $config,
        protected PrdService $prd,
        protected SpecService $specs,
    ) {}

    /**
     * @param  list<string>  $ids  Empty → every FR, journey, and NFR the PRD defines.
     * @return array{
     *     scope: string,
     *     ids: list<array{id: string, type: string, title: string|null, moscow: string|null, defined: bool, specs: list<string>}>,
     *     specs: list<array{code: string, title: string, status: string, epic: string|null, matches: list<string>, action: string, hint: string}>,
     *     untraced: list<string>,
     *     uncovered_must: list<string>,
     *     unknown: list<string>,
     *     summary: array{ids: int, specs: int, by_action: array<string, int>}
     * }
     */
    public function analyse(array $ids = []): array
    {
        $defined = PrdIds::defined($this->prd->read() ?? '');
        $requested = array_values(array_unique(array_map(
            static fn (string $id): string => strtoupper(trim($id)),
            $ids
        )));
        $scoped = $requested !== [];

        $targets = $scoped
            ? $requested
            : array_keys(array_filter(
                $defined,
                static fn (array $definition): bool => $definition['type'] !== 'Q'
            ));

        $coverage = array_fill_keys($targets, []);
        $rows = [];
        $byAction = [];

        foreach ($this->specs->allSpecs() as $spec) {
            $code = (string) ($spec['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $cited = PrdIds::cited(((string) ($spec['title'] ?? ''))."\n".((string) ($spec['body'] ?? '')));
            $matches = array_values(array_intersect($targets, $cited));

            if ($matches === []) {
                continue;
            }

            foreach ($matches as $id) {
                $coverage[$id][] = $code;
            }

            $status = (string) ($spec['status'] ?? '');
            [$action, $hint] = $this->actionFor($status, $code);
            $byAction[$action] = ($byAction[$action] ?? 0) + 1;

            $epic = $spec['epic'] ?? null;

            $rows[] = [
                'code' => $code,
                'title' => (string) ($spec['title'] ?? ''),
                'status' => $status,
                'epic' => is_array($epic) ? (string) ($epic['code'] ?? '') ?: null : null,
                'matches' => $matches,
                'action' => $action,
                'hint' => $hint,
            ];
        }

        $items = [];
        $untraced = [];
        $uncoveredMust = [];
        $unknown = [];

        foreach ($targets as $id) {
            $definition = $defined[$id] ?? null;
            $codes = $coverage[$id];

            $items[] = [
                'id' => $id,
                'type' => PrdIds::typeOf($id),
                'title' => $definition['title'] ?? null,
                'moscow' => $definition['moscow'] ?? null,
                'defined' => $definition !== null,
                'specs' => $codes,
            ];

            if ($definition === null) {
                $unknown[] = $id;

                continue;
            }

            if ($codes === []) {
                $untraced[] = $id;

                if ($definition['type'] === 'FR' && $definition['moscow'] === 'Must') {
                    $uncoveredMust[] = $id;
                }
            }
        }

        ksort($byAction);

        return [
            'scope' => $scoped ? 'ids' : 'prd',
            'ids' => $items,
            'specs' => $rows,
            'untraced' => $untraced,
            'uncovered_must' => $uncoveredMust,
            'unknown' => $unknown,
            'summary' => [
                'ids' => count($items),
                'specs' => count($rows),
                'by_action' => $byAction,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function actionFor(string $status, string $code): array
    {
        $status = strtoupper(trim($status));

        return match (true) {
            $status === strtoupper($this->config->status('planned')) => [
                'update_and_replan',
                "Re-issue {$code} through spec-add (same code, no status key), then plan it again: the plan was written against the old promise.",
            ],
            $status === strtoupper($this->config->status('in_progress')) => [
                'coordinate',
                "{$code} is being implemented. Tell the user, and on consent re-issue the spec body and carry the delta into the next task.",
            ],
            $status === strtoupper($this->config->status('review')) => [
                'rework',
                "spec-request-changes {$code} with the PRD delta as feedback.",
            ],
            $status === strtoupper($this->config->status('done')) => [
                'new_spec',
                "{$code} shipped against the old promise and is never reopened: a changed promise goes through larapilot-feature as a change request, an unmet one through larapilot-bug.",
            ],
            default => [
                'update_spec',
                "Re-issue {$code} through spec-add with the same code and no status key: the body is replaced, the status kept.",
            ],
        };
    }
}
