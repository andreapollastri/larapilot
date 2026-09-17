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

it('extracts changed files with counts and hunks for a commit range', function (): void {
    initTestGitRepository('chore: bootstrap');
    $root = base_path();

    $name = 'change-set-'.bin2hex(random_bytes(6)).'.txt';
    file_put_contents($root.'/'.$name, "line 1\nline 2\nline 3\n");
    shell_exec('git -C '.escapeshellarg($root).' add '.escapeshellarg($name).' 2>/dev/null');
    shell_exec('git -C '.escapeshellarg($root).' commit -m '.escapeshellarg('feat: add fixture').' 2>/dev/null');

    $files = app(GitService::class)->changeSet('HEAD~1..HEAD');

    $fixture = collect($files)->firstWhere('path', $name);

    expect($fixture)->not->toBeNull()
        ->and($fixture['added'])->toBe(3)
        ->and($fixture['removed'])->toBe(0)
        ->and($fixture['hunks'])->toContain('1-3');
});

it('builds github commit urls from ssh remotes', function (): void {
    withGitRemoteSandbox(function (string $root, GitService $git): void {
        expect($git->commitUrl('abc123def456'))->toBeNull();

        shell_exec('git -C '.escapeshellarg($root).' remote add origin git@github.com:andreapollastri/larapilot.git 2>/dev/null');

        expect($git->commitUrl('abc123def456'))
            ->toBe('https://github.com/andreapollastri/larapilot/commit/abc123def456')
            ->and($git->originProvider())->toBe('github');
    });
});

it('builds gitlab and bitbucket commit urls from remotes', function (): void {
    withGitRemoteSandbox(function (string $root, GitService $git): void {
        shell_exec('git -C '.escapeshellarg($root).' remote add origin git@gitlab.com:acme/app.git 2>/dev/null');

        expect($git->originProvider())->toBe('gitlab')
            ->and($git->commitUrl('abc123def456'))
            ->toBe('https://gitlab.com/acme/app/-/commit/abc123def456');

        shell_exec('git -C '.escapeshellarg($root).' remote set-url origin https://bitbucket.org/acme/app.git 2>/dev/null');

        expect($git->originProvider())->toBe('bitbucket')
            ->and($git->commitUrl('abc123def456'))
            ->toBe('https://bitbucket.org/acme/app/commits/abc123def456');
    });
});

it('detects azure devops remotes and builds commit urls', function (): void {
    withGitRemoteSandbox(function (string $root, GitService $git): void {
        shell_exec('git -C '.escapeshellarg($root).' remote add origin https://dev.azure.com/acme/checkout/_git/app 2>/dev/null');

        expect($git->originProvider())->toBe('azure')
            ->and($git->originRepoSlug())->toBe('acme/checkout/app')
            ->and($git->commitUrl('abc123def456'))
            ->toBe('https://dev.azure.com/acme/checkout/_git/app/commit/abc123def456');

        shell_exec('git -C '.escapeshellarg($root).' remote set-url origin git@ssh.dev.azure.com:v3/acme/checkout/app 2>/dev/null');

        expect($git->originProvider())->toBe('azure')
            ->and($git->originRepoSlug())->toBe('acme/checkout/app')
            ->and($git->commitUrl('abc123def456'))
            ->toBe('https://dev.azure.com/acme/checkout/_git/app/commit/abc123def456');

        shell_exec('git -C '.escapeshellarg($root).' remote set-url origin https://acme.visualstudio.com/checkout/_git/app 2>/dev/null');

        expect($git->originProvider())->toBe('azure')
            ->and($git->originRepoSlug())->toBe('acme/checkout/app')
            ->and($git->commitUrl('abc123def456'))
            ->toBe('https://acme.visualstudio.com/checkout/_git/app/commit/abc123def456');
    });
});

it('resolves merge commits that reference a spec code', function (): void {
    initTestGitRepository('feat(US-001): TASK-01 implementation');
    initTestGitRepository('Merge pull request #42 from user/feature/US-001-login');

    $commit = app(GitService::class)->resolveMergeCommit('US-001');

    expect($commit)->not->toBeNull()
        ->and($commit['subject'])->toBe('Merge pull request #42 from user/feature/US-001-login');
});

it('builds a 12-month contribution calendar from every local branch', function (): void {
    $recent = (new DateTimeImmutable('-2 months'))->format('Y-m-d').'T12:00:00+00:00';
    $older = (new DateTimeImmutable('-2 years'))->format('Y-m-d').'T12:00:00+00:00';
    $ada = 'ada-'.bin2hex(random_bytes(4)).'@example.test';
    $grace = 'grace-'.bin2hex(random_bytes(4)).'@example.test';
    $ancient = 'old-'.bin2hex(random_bytes(4)).'@example.test';

    commitTestGitChange('feat: ancient', $older, 'Old Timer', $ancient);
    commitTestGitChange('feat: ada work', $recent, 'Ada Lovelace', $ada);
    commitTestGitChange('feat: ada again', $recent, 'Ada Lovelace', $ada);
    commitTestGitChange('feat: grace work', $recent, 'Grace Hopper', $grace);

    $git = app(GitService::class);
    $all = $git->contributionActivity();
    $adaActivity = $git->contributionActivity($ada);
    $authors = collect($all['authors'])->keyBy('email');

    expect($all['is_repository'])->toBeTrue()
        ->and($all['weeks'])->not->toBeEmpty()
        ->and($authors->get($ada)['commits'] ?? 0)->toBe(2)
        ->and($authors->get($grace)['commits'] ?? 0)->toBe(1)
        ->and($authors->has($ancient))->toBeFalse()
        ->and($adaActivity['total'])->toBe(2)
        ->and($adaActivity['selected_author'])->toBe($ada);

    $counted = 0;

    foreach ($adaActivity['weeks'] as $week) {
        foreach ($week as $day) {
            $counted += $day['count'];
        }
    }

    expect($counted)->toBe(2);
});
