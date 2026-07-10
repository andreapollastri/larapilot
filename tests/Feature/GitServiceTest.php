<?php

declare(strict_types=1);

use Larapilot\Services\GitService;

it('resolves the most recent commit that references the spec and task', function (): void {
    initTestGitRepository('feat(US-001): TASK-02 older attempt');
    initTestGitRepository('feat(US-001): TASK-01 Create model');

    $commit = app(GitService::class)->resolveTaskCommit('US-001', 'TASK-01');

    expect($commit)->not->toBeNull()
        ->and($commit['subject'])->toBe('feat(US-001): TASK-01 Create model');
});

it('builds github commit urls from ssh remotes', function (): void {
    $git = app(GitService::class);
    $root = base_path();

    if (! is_dir($root.'/.git')) {
        initTestGitRepository('chore: bootstrap');
    }

    shell_exec('git -C '.escapeshellarg($root).' remote remove origin 2>/dev/null');

    expect($git->commitUrl('abc123def456'))->toBeNull();

    shell_exec('git -C '.escapeshellarg($root).' remote add origin git@github.com:andreapollastri/larapilot.git 2>/dev/null');

    expect($git->commitUrl('abc123def456'))
        ->toBe('https://github.com/andreapollastri/larapilot/commit/abc123def456');
});

it('resolves merge commits that reference a spec code', function (): void {
    initTestGitRepository('feat(US-001): TASK-01 implementation');
    initTestGitRepository('Merge pull request #42 from user/feature/US-001-login');

    $commit = app(GitService::class)->resolveMergeCommit('US-001');

    expect($commit)->not->toBeNull()
        ->and($commit['subject'])->toBe('Merge pull request #42 from user/feature/US-001-login');
});
