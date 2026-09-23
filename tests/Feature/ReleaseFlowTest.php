<?php

declare(strict_types=1);

use Larapilot\Services\GitService;
use Larapilot\Services\ReleaseFlowService;
use Larapilot\Services\ReleaseService;

it('rejects release git commands when release mode is disabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'NO'])->assertSuccessful();

    foreach ([
        ['larapilot:release-cut', ['--semver' => '1.2.0']],
        ['larapilot:release-feature', ['--spec' => 'US-001', '--semver' => '1.2.0']],
        ['larapilot:release-sync', ['--semver' => '1.2.0']],
        ['larapilot:release-ship', ['--semver' => '1.2.0']],
    ] as [$command, $parameters]) {
        $this->artisan($command, $parameters)
            ->assertExitCode(4)
            ->expectsOutputToContain('E_PRECONDITION');
    }
});

it('release-list tells the agent when it must choose a release branch', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'YES'])->assertSuccessful();

    $this->artisan('larapilot:release-list')
        ->assertSuccessful()
        ->expectsOutputToContain('"needs_choice"');
});

it('cuts a release branch from develop and checks it out', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases, GitService $git): void {
        $releases->add('1.2.0', 'Next', 'planned');

        $result = $flow->cut('1.2.0', true);

        expect($result['ok'])->toBeTrue()
            ->and($result['created'])->toBeTrue()
            ->and($result['checked_out'])->toBeTrue()
            ->and($result['branch'])->toBe('release/1.2.0')
            ->and($git->currentBranch())->toBe('release/1.2.0')
            ->and($releases->find('1.2.0')['status'])->toBe('in_progress');

        unset($root);
    });
});

it('creates develop from main when the integration branch is missing', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases, GitService $git): void {
        $git->run('branch', '-D', 'develop');
        $releases->add('1.2.0', 'Next', 'planned');

        $result = $flow->cut('1.2.0', false);

        expect($result['ok'])->toBeTrue()
            ->and($result['develop_created'])->toBeTrue()
            ->and($git->localBranchExists('develop'))->toBeTrue()
            ->and($git->localBranchExists('release/1.2.0'))->toBeTrue()
            ->and($git->currentBranch())->toBe('main');

        unset($root);
    });
});

it('does not switch branches when the working tree is dirty', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases, GitService $git): void {
        $releases->add('1.2.0', 'Next', 'planned');
        file_put_contents($root.'/README.md', "wip\n");

        $result = $flow->cut('1.2.0', true);

        expect($result['ok'])->toBeTrue()
            ->and($result['created'])->toBeTrue()
            ->and($result['checked_out'])->toBeFalse()
            ->and($git->currentBranch())->toBe('main')
            ->and($result['reason'])->toContain('dirty');
    });
});

it('creates the feature branch from the release branch', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases, GitService $git): void {
        $releases->add('1.2.0', 'Next', 'planned', ['US-014']);

        $result = $flow->feature('1.2.0', 'us-014', 'Billing flow', true);

        expect($result['ok'])->toBeTrue()
            ->and($result['branch'])->toBe('feature/US-014-billing-flow')
            ->and($result['base'])->toBe('release/1.2.0')
            ->and($result['checked_out'])->toBeTrue()
            ->and($git->currentBranch())->toBe('feature/US-014-billing-flow')
            ->and($releases->find('1.2.0')['status'])->toBe('in_progress');

        unset($root);
    });
});

it('asks for a version only when several releases are in progress', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases): void {
        $releases->add('1.2.0', 'Next', 'in_progress');
        $releases->add('1.3.0', 'Later', 'in_progress');

        $context = $flow->context();

        expect($context['needs_choice'])->toBeTrue()
            ->and($context['active_release'])->toBeNull();

        expect(fn () => $flow->feature(null, 'US-001', null))
            ->toThrow(InvalidArgumentException::class, '--semver');

        $flow->cut('1.2.0', true);
        $onBranch = $flow->context();

        expect($onBranch['needs_choice'])->toBeFalse()
            ->and($onBranch['active_release'])->toBe('1.2.0');

        unset($root);
    });
});

it('merges develop into the release branch', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases, GitService $git): void {
        $releases->add('1.2.0', 'Next', 'planned');
        $flow->cut('1.2.0', false);
        $git->run('checkout', 'develop');
        file_put_contents($root.'/from-develop.txt', "integration\n");
        $git->run('add', 'from-develop.txt');
        $git->run('commit', '-m', 'feat: integration');

        $result = $flow->sync('1.2.0');

        expect($result['ok'])->toBeTrue()
            ->and($git->currentBranch())->toBe('release/1.2.0')
            ->and($git->run('show', 'release/1.2.0:from-develop.txt')['ok'])->toBeTrue();
    });
});

it('ships the release onto main, tags it, and back-merges develop', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases, GitService $git): void {
        $releases->add('1.2.0', 'Next', 'planned');
        $flow->cut('1.2.0', true);
        file_put_contents($root.'/shipped.txt', "from release\n");
        $git->run('add', 'shipped.txt');
        expect($git->run('commit', '-m', 'feat: ship me')['ok'])->toBeTrue();

        $result = $flow->ship('1.2.0');

        expect($result['ok'])->toBeTrue()
            ->and($result['tag'])->toBe('v1.2.0')
            ->and($result['tag_created'])->toBeTrue()
            ->and($git->currentBranch())->toBe('develop')
            ->and($releases->find('1.2.0')['status'])->toBe('shipped')
            ->and($git->run('show', 'main:shipped.txt')['output'])->toContain('from release')
            ->and($git->run('show', 'develop:shipped.txt')['output'])->toContain('from release')
            ->and($git->run('rev-parse', 'v1.2.0')['ok'])->toBeTrue();
    });
});

it('aborts a conflicting ship and leaves the release in progress', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases, GitService $git): void {
        $releases->add('1.2.0', 'Next', 'planned');
        $flow->cut('1.2.0', true);
        file_put_contents($root.'/README.md', "release\n");
        $git->run('add', 'README.md');
        $git->run('commit', '-m', 'feat: release readme');

        $git->run('checkout', 'develop');
        file_put_contents($root.'/README.md', "develop\n");
        $git->run('add', 'README.md');
        $git->run('commit', '-m', 'feat: develop readme');

        $result = $flow->ship('1.2.0');

        expect($result['ok'])->toBeFalse()
            ->and($result['error'])->toContain('develop')
            ->and($releases->find('1.2.0')['status'])->toBe('in_progress')
            ->and($git->run('rev-parse', '-q', '--verify', 'MERGE_HEAD')['ok'])->toBeFalse();
    });
});

it('refuses to ship a dirty working tree', function (): void {
    withReleaseFlowSandbox(function (string $root, ReleaseFlowService $flow, ReleaseService $releases): void {
        $releases->add('1.2.0', 'Next', 'in_progress');
        $flow->cut('1.2.0', false);
        file_put_contents($root.'/README.md', "wip\n");

        $result = $flow->ship('1.2.0');

        expect($result['ok'])->toBeFalse()
            ->and($result['error'])->toContain('dirty')
            ->and($releases->find('1.2.0')['status'])->toBe('in_progress');
    });
});
