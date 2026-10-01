<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

/**
 * How a team writes its commit subjects, read from its history and its
 * tooling: Conventional Commits or not, which types, whether it uses scopes
 * and which, commitlint, commit hooks. A commit written for a task follows
 * it and still carries `{code} TASK-NN`, so `task-done` finds it.
 */
final class CommitStyle
{
    protected const CONVENTIONAL = '/^([a-z]+)(?:\(([^)]+)\))?!?:\s+\S/i';

    /**
     * @return array<string, mixed>|null
     */
    public static function read(string $gitRoot): ?array
    {
        $subjects = RepoGit::subjects($gitRoot, 60);

        if ($subjects === []) {
            return null;
        }

        $conventional = 0;
        $scoped = 0;
        $types = [];
        $scopes = [];

        foreach ($subjects as $subject) {
            if (preg_match(self::CONVENTIONAL, $subject, $matches) !== 1) {
                continue;
            }

            $conventional++;
            $type = strtolower($matches[1]);
            $types[$type] = ($types[$type] ?? 0) + 1;

            if (($matches[2] ?? '') !== '') {
                $scoped++;
                $scopes[$matches[2]] = ($scopes[$matches[2]] ?? 0) + 1;
            }
        }

        arsort($types);
        arsort($scopes);

        $declared = self::declaredScopes($gitRoot);
        $isConventional = $conventional * 10 >= count($subjects) * 6;
        // Scopes count when the history uses them; scopes only declared in
        // an editor setting are offered, not imposed.
        $usesScopes = $isConventional && $scoped * 2 >= $conventional;

        return array_filter([
            'conventional' => sprintf('%d of %d subjects', $conventional, count($subjects)),
            'types' => $isConventional ? array_slice(array_keys($types), 0, 8) : null,
            'scopes' => $scopes === [] ? null : array_slice(array_map('strval', array_keys($scopes)), 0, 8),
            'declared_scopes' => $declared === [] ? null : $declared,
            'commitlint' => self::hasCommitlint($gitRoot),
            'hooks' => self::hooks($gitRoot) ?: null,
            'samples' => array_slice($subjects, 0, 4),
            'pattern' => match (true) {
                $usesScopes => '<type>(<scope>): {code} TASK-NN <summary>',
                $isConventional => '<type>: {code} TASK-NN <summary>',
                default => '{code} TASK-NN <summary>',
            },
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return list<string>
     */
    protected static function declaredScopes(string $root): array
    {
        $settings = RepoFiles::json($root.'/.vscode/settings.json');
        $scopes = $settings['conventionalCommits.scopes'] ?? [];

        return array_values(array_filter(is_array($scopes) ? $scopes : [], 'is_string'));
    }

    protected static function hasCommitlint(string $root): bool
    {
        foreach (array_merge(
            RepoFiles::configNames('commitlint.config'),
            ['.commitlintrc', '.commitlintrc.json', '.commitlintrc.yml', '.commitlintrc.yaml', '.commitlintrc.js', '.commitlintrc.cjs', '.commitlintrc.ts']
        ) as $file) {
            if (is_file($root.'/'.$file)) {
                return true;
            }
        }

        return isset(RepoFiles::json($root.'/package.json')['commitlint']);
    }

    /**
     * @return list<string>
     */
    protected static function hooks(string $root): array
    {
        $hooks = [];

        if (is_dir($root.'/.husky')) {
            $hooks[] = 'husky';
        }

        foreach (['lefthook.yml', 'lefthook.yaml', '.lefthook.yml'] as $file) {
            if (is_file($root.'/'.$file)) {
                $hooks[] = 'lefthook';

                break;
            }
        }

        $package = RepoFiles::json($root.'/package.json');

        if (is_file($root.'/.lintstagedrc') || is_file($root.'/.lintstagedrc.json') || isset($package['lint-staged'])) {
            $hooks[] = 'lint-staged';
        }

        return $hooks;
    }
}
