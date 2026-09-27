<?php

declare(strict_types=1);

use Larapilot\Services\GitGraphService;
use Larapilot\Services\GitService;

/**
 * A small Gitflow history in an isolated repository: two features merged
 * (one of them deleted afterwards), a release, a hotfix, a feature still
 * open, and one that fell behind.
 *
 * @param  callable(GitGraphService $graph, GitService $git, string $root): void  $callback
 */
function withGitflowSandbox(callable $callback): void
{
    withGitRemoteSandbox(function (string $root, GitService $git) use ($callback): void {
        $clock = 0;

        $run = static function (string $command, ?string $date = null) use ($root): void {
            $env = $date !== null
                ? 'GIT_AUTHOR_DATE='.escapeshellarg($date).' GIT_COMMITTER_DATE='.escapeshellarg($date).' '
                : '';

            shell_exec($env.'git -C '.escapeshellarg($root).' '.$command.' 2>/dev/null');
        };

        $when = static function () use (&$clock): string {
            $clock++;

            return sprintf('2026-08-%02dT10:00:00+00:00', $clock);
        };

        $commit = static function (string $message, string $name = 'Ada Lovelace', string $email = 'ada@example.test') use ($root, $run, $when, &$clock): void {
            file_put_contents($root.'/change-'.($clock + 1).'.txt', $message);
            $run('add -A');
            $run('-c user.name='.escapeshellarg($name).' -c user.email='.escapeshellarg($email).' commit -m '.escapeshellarg($message), $when());
        };

        $merge = static function (string $branch, string $message) use ($run, $when): void {
            $run('merge --no-ff '.escapeshellarg($branch).' -m '.escapeshellarg($message), $when());
        };

        // `init` is the commit the sandbox starts with, on main.
        $run('tag v0.1.0');
        $run('checkout -b develop');
        $commit('docs: backlog');

        $run('checkout -b feature/US-001-sign-in');
        $commit('feat(US-001): TASK-01 users table', 'Grace Hopper', 'grace@example.test');
        $commit('feat(US-001): TASK-02 sign-in form', 'Grace Hopper', 'grace@example.test');

        $run('checkout develop');
        $run('checkout -b feature/US-002-reset');
        $commit('feat(US-002): TASK-01 reset token');

        $run('checkout develop');
        $merge('feature/US-001-sign-in', "Merge branch 'feature/US-001-sign-in' into develop");
        $merge('feature/US-002-reset', "Merge branch 'feature/US-002-reset' into develop");
        // merged and deleted: its commits stay, its name is gone
        $run('branch -D feature/US-002-reset');

        $run('checkout -b release/1.0.0');
        $commit('chore(release): 1.0.0');
        $run('checkout main');
        $merge('release/1.0.0', "Merge branch 'release/1.0.0'");
        $run('tag -a v1.0.0 -m "First release"');

        $run('checkout develop');
        $merge('main', "Merge branch 'main' into develop");

        $run('checkout -b feature/US-004-invoice');
        $commit('feat(US-004): TASK-01 invoice model');

        $run('checkout main');
        $run('checkout -b hotfix/1.0.1');
        $commit('fix: reset link expired early');
        $run('checkout main');
        $merge('hotfix/1.0.1', "Merge branch 'hotfix/1.0.1'");

        $run('checkout develop');
        $commit('docs: developer notes');
        $run('checkout -b feature/US-007-pay');
        $commit('feat(US-007): TASK-01 checkout session');
        $run('checkout develop');

        $callback(new GitGraphService($git), $git, $root);
    });
}

it('finds the main line, the integration branch, and where the project stands', function (): void {
    withGitflowSandbox(function (GitGraphService $graph): void {
        $data = $graph->graph();

        expect($data['available'])->toBeTrue()
            ->and($data['primary'])->toBe('main')
            ->and($data['develop'])->toBe('develop')
            ->and($data['current'])->toBe('develop')
            ->and($data['detached'])->toBeFalse()
            ->and($data['truncated'])->toBeFalse()
            ->and($data['total'])->toBe(count($data['commits']))
            ->and(array_column($data['tags'], 'name'))->toBe(['v1.0.0', 'v0.1.0']);
    });
});

it('puts every commit on the branch it was made on', function (): void {
    withGitflowSandbox(function (GitGraphService $graph): void {
        $commits = collect($graph->graph()['commits'])->keyBy('subject');

        $expected = [
            'init' => ['main', 'main'],
            "Merge branch 'release/1.0.0'" => ['main', 'main'],
            "Merge branch 'hotfix/1.0.1'" => ['main', 'main'],
            'docs: backlog' => ['develop', 'develop'],
            "Merge branch 'feature/US-001-sign-in' into develop" => ['develop', 'develop'],
            "Merge branch 'main' into develop" => ['develop', 'develop'],
            'docs: developer notes' => ['develop', 'develop'],
            'feat(US-001): TASK-01 users table' => ['feature/US-001-sign-in', 'work'],
            'feat(US-001): TASK-02 sign-in form' => ['feature/US-001-sign-in', 'work'],
            'feat(US-004): TASK-01 invoice model' => ['feature/US-004-invoice', 'work'],
            'feat(US-007): TASK-01 checkout session' => ['feature/US-007-pay', 'work'],
            'chore(release): 1.0.0' => ['release/1.0.0', 'ship'],
            'fix: reset link expired early' => ['hotfix/1.0.1', 'ship'],
        ];

        foreach ($expected as $subject => [$branch, $kind]) {
            expect($commits[$subject]['branch'])->toBe($branch, $subject)
                ->and($commits[$subject]['kind'])->toBe($kind, $subject)
                ->and($commits[$subject]['gone'])->toBeFalse($subject);
        }

        // The deleted branch is named from the merge that closed it.
        $orphan = $commits['feat(US-002): TASK-01 reset token'];

        expect($orphan['branch'])->toBe('feature/US-002-reset')
            ->and($orphan['kind'])->toBe('work')
            ->and($orphan['gone'])->toBeTrue()
            ->and($orphan['spec'])->toBe('US-002');

        expect($commits["Merge branch 'release/1.0.0'"]['is_merge'])->toBeTrue()
            ->and($commits['init']['is_root'])->toBeTrue()
            ->and($commits['feat(US-001): TASK-01 users table']['author'])->toBe('Grace Hopper');
    });
});

it('keeps the main line on the first lane and the integration branch on the second', function (): void {
    withGitflowSandbox(function (GitGraphService $graph): void {
        $data = $graph->graph();

        foreach ($data['commits'] as $commit) {
            $lane = match ($commit['branch']) {
                'main' => 0,
                'develop' => 1,
                default => null,
            };

            if ($lane !== null) {
                expect($commit['lane'])->toBe($lane, $commit['subject']);
            } else {
                expect($commit['lane'])->toBeGreaterThanOrEqual(2, $commit['subject']);
            }

            // Every lane a row draws on is inside the width of the graph.
            foreach (['in', 'through', 'out'] as $part) {
                foreach ($commit[$part] as $line) {
                    expect($line['lane'])->toBeLessThan($data['lanes'])
                        ->and(array_key_exists($line['kind'], GitGraphService::KINDS))->toBeTrue();
                }
            }

            // No two lines of a row share a lane they pass through.
            $through = array_column($commit['through'], 'lane');

            expect($through)->toBe(array_values(array_unique($through)))
                ->and(in_array($commit['lane'], $through, true))->toBeFalse($commit['subject']);
        }

        $rows = collect($data['commits'])->keyBy('subject');

        // A merge leaves its dot twice: down its own lane and to what it merged.
        expect($rows["Merge branch 'feature/US-001-sign-in' into develop"]['out'])->toHaveCount(2)
            ->and($rows["Merge branch 'feature/US-001-sign-in' into develop"]['out'][0]['lane'])->toBe(1)
            ->and($rows["Merge branch 'feature/US-001-sign-in' into develop"]['out'][1]['kind'])->toBe('work')
            // a root has nowhere to go
            ->and($rows['init']['out'])->toBe([])
            // the first commit is where develop came from
            ->and(array_column($rows['init']['in'], 'lane'))->toContain(0, 1);

        expect($data['kinds'])->toBe(['main' => 3, 'develop' => 5, 'work' => 5, 'ship' => 2]);
    });
});

it('measures every branch against the branch it is heading for', function (): void {
    withGitflowSandbox(function (GitGraphService $graph): void {
        $branches = collect($graph->graph()['branches'])->keyBy('name');

        expect($branches->keys()->take(2)->all())->toBe(['main', 'develop'])
            ->and($branches['main']['target'])->toBeNull()
            ->and($branches['main']['state'])->toBeNull()
            ->and($branches['main']['type'])->toBe('main')
            ->and($branches['develop']['target'])->toBe('main')
            ->and($branches['develop']['current'])->toBeTrue()
            ->and($branches['develop']['state']['key'])->toBe('diverged')
            ->and($branches['feature/US-007-pay']['target'])->toBe('develop')
            ->and($branches['feature/US-007-pay']['ahead'])->toBe(1)
            ->and($branches['feature/US-007-pay']['behind'])->toBe(0)
            ->and($branches['feature/US-007-pay']['state'])->toBe(['key' => 'ahead', 'label' => '1 commit to merge into develop'])
            ->and($branches['feature/US-007-pay']['spec'])->toBe('US-007')
            ->and($branches['feature/US-004-invoice']['ahead'])->toBe(1)
            ->and($branches['feature/US-004-invoice']['behind'])->toBe(1)
            ->and($branches['feature/US-004-invoice']['state']['label'])->toBe('1 commit to merge, 1 behind develop')
            ->and($branches['feature/US-001-sign-in']['state']['key'])->toBe('merged')
            ->and($branches['feature/US-001-sign-in']['state']['label'])->toBe('Merged into develop')
            // what ships is measured against the main line
            ->and($branches['release/1.0.0']['target'])->toBe('main')
            ->and($branches['release/1.0.0']['type'])->toBe('release')
            ->and($branches['release/1.0.0']['state']['label'])->toBe('Merged into main')
            ->and($branches['hotfix/1.0.1']['target'])->toBe('main')
            ->and($branches['hotfix/1.0.1']['type'])->toBe('hotfix')
            ->and($branches['hotfix/1.0.1']['state']['key'])->toBe('merged')
            ->and($branches['develop']['upstream_state']['key'])->toBe('local')
            ->and($branches->has('feature/US-002-reset'))->toBeFalse();
    });
});

it('labels the commits that branches and tags point to', function (): void {
    withGitflowSandbox(function (GitGraphService $graph): void {
        $commits = collect($graph->graph()['commits'])->keyBy('subject');
        $names = static fn (array $commit): array => array_map(
            static fn (array $ref): string => $ref['type'].':'.$ref['name'],
            $commit['refs']
        );

        expect($names($commits['docs: developer notes']))->toBe(['current:develop'])
            ->and($names($commits["Merge branch 'hotfix/1.0.1'"]))->toBe(['branch:main'])
            ->and($names($commits["Merge branch 'release/1.0.0'"]))->toBe(['tag:v1.0.0'])
            ->and($names($commits['init']))->toBe(['tag:v0.1.0'])
            ->and($names($commits['feat(US-007): TASK-01 checkout session']))->toBe(['branch:feature/US-007-pay'])
            ->and($commits['docs: developer notes']['refs'][0]['kind'])->toBe('develop')
            ->and($commits['init']['refs'][0]['kind'])->toBeNull();
    });
});

it('draws the newest commits only and says that older ones exist', function (): void {
    withGitflowSandbox(function (GitGraphService $graph): void {
        $all = $graph->graph();
        // the floor of the limit is 20
        $some = $graph->graph(5);

        expect($all['total'])->toBe(15)
            ->and($some['limit'])->toBe(20)
            ->and($some['truncated'])->toBeFalse();
    });

    withGitRemoteSandbox(function (string $root, GitService $git): void {
        for ($i = 1; $i <= 24; $i++) {
            file_put_contents($root.'/n.txt', (string) $i);
            shell_exec('git -C '.escapeshellarg($root).' add -A 2>/dev/null');
            shell_exec('git -C '.escapeshellarg($root).' commit -m '.escapeshellarg('change '.$i).' 2>/dev/null');
        }

        $data = (new GitGraphService($git))->graph(20);

        expect($data['commits'])->toHaveCount(20)
            ->and($data['total'])->toBe(25)
            ->and($data['truncated'])->toBeTrue()
            ->and($data['commits'][0]['subject'])->toBe('change 24')
            // the line runs off the bottom, towards what was not drawn
            ->and($data['commits'][19]['out'])->toBe([['lane' => 0, 'kind' => 'main']])
            ->and($data['lanes'])->toBe(1);
    });
});

it('reads a repository that follows no branching model', function (): void {
    withGitRemoteSandbox(function (string $root, GitService $git): void {
        shell_exec('git -C '.escapeshellarg($root).' branch -m trunk-line 2>/dev/null');
        shell_exec('git -C '.escapeshellarg($root).' checkout -b experiment 2>/dev/null');
        file_put_contents($root.'/x.txt', 'x');
        shell_exec('git -C '.escapeshellarg($root).' add -A 2>/dev/null');
        shell_exec('git -C '.escapeshellarg($root).' commit -m "try something" 2>/dev/null');

        $service = new GitGraphService($git);
        $data = $service->graph();

        // Nothing is called main: the branch checked out is what the rest is read against.
        expect($data['primary'])->toBe('experiment')
            ->and($data['develop'])->toBeNull()
            ->and(collect($data['branches'])->firstWhere('name', 'trunk-line')['target'])->toBe('experiment')
            ->and(collect($data['branches'])->firstWhere('name', 'trunk-line')['state']['key'])->toBe('merged');

        expect($service->classify('feat/login', 'main', 'develop'))->toBe(['kind' => 'work', 'type' => 'feature'])
            ->and($service->classify('fix/typo', 'main', 'develop'))->toBe(['kind' => 'work', 'type' => 'bugfix'])
            ->and($service->classify('chore/cleanup', 'main', 'develop'))->toBe(['kind' => 'other', 'type' => 'chore'])
            ->and($service->classify('spike', 'main', 'develop'))->toBe(['kind' => 'other', 'type' => 'branch'])
            ->and($service->specCode('feature/us-12-login'))->toBe('US-12')
            ->and($service->specCode('feature/cleanup'))->toBeNull()
            ->and($service->specCode('release/1.0.0'))->toBeNull();
    });
});

it('fades the commits of other developers when one is selected', function (): void {
    withGitflowSandbox(function (GitGraphService $graph): void {
        $commits = collect($graph->graph(150, 'GRACE@example.test')['commits'])->keyBy('subject');

        expect($commits['feat(US-001): TASK-01 users table']['dim'])->toBeFalse()
            ->and($commits['docs: backlog']['dim'])->toBeTrue();

        expect(collect($graph->graph()['commits'])->where('dim', true)->count())->toBe(0);
    });
});

it('draws the branches and the history on the git page', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['code' => 'US-001', 'title' => 'Login']);
    commitTestGitChange('feat(US-001): TASK-01 graph on the page');

    $this->get('/larapilot/git')
        ->assertOk()
        ->assertSee('Contribution graph', false)
        ->assertSee('id="branches-title"', false)
        ->assertSee('Against its target', false)
        ->assertSee('On the remote', false)
        ->assertSee('You are here', false)
        ->assertSee('id="history-title"', false)
        ->assertSee('<ol class="graph"', false)
        ->assertSee('class="dot"', false)
        ->assertSee('feat(US-001): TASK-01 graph on the page', false)
        ->assertSee('What the colours stand for', false)
        ->assertSee('How to read this', false)
        // a commit that names a spec of the backlog leads to it
        ->assertSee('href="'.url('/larapilot/specs/US-001').'"', false)
        ->assertDontSee('@endif', false)
        ->assertDontSee('@if', false);

    $this->get('/larapilot/git?commits=abc&author[]=x')->assertOk();
    $this->get('/larapilot/git?commits=999999')->assertOk();
});
