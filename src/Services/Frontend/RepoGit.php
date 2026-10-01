<?php

declare(strict_types=1);

namespace Larapilot\Services\Frontend;

use Symfony\Component\Process\Process;

/**
 * The few git questions the scan asks the frontend repository. Paths come
 * back relative to the frontend root even when it is a folder inside a
 * larger repository.
 */
final class RepoGit
{
    /**
     * @param  list<string>  $arguments
     */
    public static function run(string $root, array $arguments, int $timeout = 20): ?string
    {
        $process = new Process(array_merge(['git', '-C', $root], $arguments), null, ['GIT_TERMINAL_PROMPT' => '0'], null, $timeout);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return $process->isSuccessful() ? rtrim($process->getOutput()) : null;
    }

    public static function isRepository(string $root): bool
    {
        return self::run($root, ['rev-parse', '--is-inside-work-tree']) === 'true';
    }

    public static function head(string $root): ?string
    {
        $head = self::run($root, ['rev-parse', 'HEAD']);

        return is_string($head) && $head !== '' ? $head : null;
    }

    /**
     * The repository a folder belongs to — a project cloned inside a
     * monorepo checkout has its own.
     */
    public static function toplevel(string $directory): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $top = self::run($directory, ['rev-parse', '--show-toplevel']);

        return is_string($top) && $top !== '' ? (realpath($top) ?: $top) : null;
    }

    /**
     * Subjects of the latest commits, merges left out, newest first.
     *
     * @return list<string>
     */
    public static function subjects(string $root, int $count = 60): array
    {
        $output = self::run($root, ['log', '--no-merges', '--format=%s', '-n', (string) $count]);

        if ($output === null || $output === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $output)), static fn (string $line): bool => $line !== ''));
    }

    public static function branch(string $root): ?string
    {
        $branch = self::run($root, ['rev-parse', '--abbrev-ref', 'HEAD']);

        return is_string($branch) && $branch !== '' && $branch !== 'HEAD' ? $branch : null;
    }

    /**
     * The branch `affected` compares against, as a ref that exists here:
     * `origin/main` when the remote branch is known, `main` otherwise.
     *
     * @return array{branch: string, ref: string, source: string}
     */
    public static function defaultBase(string $root, ?string $configured): array
    {
        if (is_string($configured) && trim($configured) !== '') {
            $branch = preg_replace('#^origin/#', '', trim($configured)) ?? trim($configured);

            return ['branch' => $branch, 'ref' => self::ref($root, $branch), 'source' => 'nx.json'];
        }

        $remoteHead = self::run($root, ['symbolic-ref', '--quiet', 'refs/remotes/origin/HEAD']);

        if (is_string($remoteHead) && str_starts_with($remoteHead, 'refs/remotes/origin/')) {
            $branch = substr($remoteHead, strlen('refs/remotes/origin/'));

            return ['branch' => $branch, 'ref' => 'origin/'.$branch, 'source' => 'origin/HEAD'];
        }

        foreach (['main', 'master', 'develop'] as $candidate) {
            if (self::run($root, ['rev-parse', '--verify', '--quiet', 'refs/remotes/origin/'.$candidate]) !== null) {
                return ['branch' => $candidate, 'ref' => 'origin/'.$candidate, 'source' => 'remote'];
            }

            if (self::run($root, ['rev-parse', '--verify', '--quiet', 'refs/heads/'.$candidate]) !== null) {
                return ['branch' => $candidate, 'ref' => $candidate, 'source' => 'local'];
            }
        }

        return ['branch' => 'main', 'ref' => 'main', 'source' => 'default'];
    }

    protected static function ref(string $root, string $branch): string
    {
        return self::run($root, ['rev-parse', '--verify', '--quiet', 'refs/remotes/origin/'.$branch]) !== null
            ? 'origin/'.$branch
            : $branch;
    }

    /**
     * Files touched by recent commits under a folder, newest first.
     *
     * @return list<string>
     */
    public static function recentFiles(string $root, string $directory, int $commits = 200): array
    {
        $arguments = ['log', '--no-merges', '--relative', '--name-only', '--format=', '-n', (string) $commits];

        if ($directory !== '.' && $directory !== '') {
            $arguments[] = '--';
            $arguments[] = $directory;
        }

        $output = self::run($root, $arguments, 30);

        if ($output === null || $output === '') {
            return [];
        }

        $files = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line !== '' && ! isset($files[$line])) {
                $files[$line] = true;
            }
        }

        return array_keys($files);
    }
}
