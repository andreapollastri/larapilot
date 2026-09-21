<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\GitService;
use Larapilot\Services\PlanService;
use Larapilot\Tests\DisabledTestCase;
use Larapilot\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class)->in('Feature');
uses(DisabledTestCase::class)->in('Disabled');

function validSpecBody(): string
{
    return <<<'MD'
**User Story**
As a user,
I want to log in,
so that I can access my account.

**Demonstrates**
After implementing this spec, login works end to end.

**Acceptance Criteria**
- [ ] Happy path
- [ ] Error case
MD;
}

function validPrd(): string
{
    return <<<'MD'
# Product

## Elevator Pitch
A thing.

## Vision
Make it great.

## User Personas
Developers.

## Functional Requirements
- Login

## MVP Scope
Login only.

## Technical Architecture
Laravel monolith.
MD;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function specsPayload(array $overrides = []): array
{
    return [
        'specs' => [
            array_merge([
                'code' => 'US-001',
                'title' => 'Login',
                'priority' => 'HIGH',
                'points' => 3,
                'status' => 'TODO',
                'body' => validSpecBody(),
            ], $overrides),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function planPayload(): array
{
    return [
        'plan_body' => 'Technical solution and test strategy.',
        'tasks' => [
            [
                'id' => 'TASK-01',
                'title' => 'Create model',
                'type' => 'implementation',
                'status' => 'TODO',
                'body' => "## Description\nCreate the model.",
            ],
            [
                'id' => 'TASK-02',
                'title' => 'Write tests',
                'type' => 'test',
                'status' => 'TODO',
                'body' => "## Description\nWrite the tests.",
                'dependencies' => ['TASK-01'],
            ],
        ],
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function payloadFile(array $payload, string $name = 'tmp-payload.yaml'): string
{
    $path = base_path('.larapilot/'.$name);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, Yaml::dump($payload, 4, 2));

    return $path;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function addSpec(array $overrides = []): void
{
    test()->artisan('larapilot:spec-add', ['--file' => payloadFile(specsPayload($overrides))])
        ->assertSuccessful();
}

function enableComments(): void
{
    test()->artisan('larapilot:settings-set', ['--comments' => 'YES'])->assertSuccessful();
}

/**
 * Run callback against an isolated git repository so remote URL tests do not
 * mutate the shared Testbench origin.
 *
 * @param  callable(string $root, GitService $git): void  $callback
 */
function withGitRemoteSandbox(callable $callback): void
{
    // Outside the repository: a sandbox that leaks on an aborted run must never
    // end up as a committed artifact.
    $sandbox = sys_get_temp_dir().'/larapilot-git-remote-sandbox-'.bin2hex(random_bytes(8));

    if (is_dir($sandbox)) {
        shell_exec('rm -rf '.escapeshellarg($sandbox));
    }

    mkdir($sandbox, 0755, true);
    shell_exec('git init -b main --template= '.escapeshellarg($sandbox));
    file_put_contents($sandbox.'/README.md', "sandbox\n");
    shell_exec('git -C '.escapeshellarg($sandbox).' config user.email test@example.com');
    shell_exec('git -C '.escapeshellarg($sandbox).' config user.name "Test User"');
    shell_exec('git -C '.escapeshellarg($sandbox).' add README.md');
    shell_exec('git -C '.escapeshellarg($sandbox).' commit -m init');

    try {
        $sandboxConfig = new class($sandbox) extends ConfigService
        {
            public function __construct(private readonly string $root) {}

            public function projectRoot(): string
            {
                return $this->root;
            }
        };

        $callback($sandbox, new GitService($sandboxConfig));
    } finally {
        shell_exec('rm -rf '.escapeshellarg($sandbox));
    }
}

function planSpec(string $code = 'US-001'): void
{
    test()->artisan('larapilot:spec-plan', ['code' => $code, '--file' => payloadFile(planPayload(), 'tmp-plan.yaml')])
        ->assertSuccessful();
}

/**
 * Mark every plan task done so the spec can move to REVIEW (spec-review
 * blocks on incomplete tasks).
 */
function completeTasks(string $code = 'US-001'): void
{
    $plan = app(PlanService::class)->read($code) ?? [];

    foreach (($plan['tasks'] ?? []) as $task) {
        if (is_array($task) && isset($task['id'])) {
            test()->artisan('larapilot:task-done', ['code' => $code, 'taskId' => (string) $task['id']])
                ->assertSuccessful();
        }
    }
}

function initTestGitRepository(string $commitMessage): string
{
    return commitTestGitChange($commitMessage);
}

function commitTestGitChange(
    string $commitMessage,
    ?string $authorDate = null,
    string $authorName = 'Test User',
    string $authorEmail = 'test@example.com',
): string {
    $root = base_path();

    if (! is_dir($root.'/.git')) {
        shell_exec('git -C '.escapeshellarg($root).' init 2>/dev/null');
        shell_exec('git -C '.escapeshellarg($root).' config user.email test@example.com 2>/dev/null');
        shell_exec('git -C '.escapeshellarg($root).' config user.name "Test User" 2>/dev/null');
    }

    file_put_contents($root.'/git-test-marker.txt', uniqid('', true));
    shell_exec('git -C '.escapeshellarg($root).' add git-test-marker.txt 2>/dev/null');

    $env = '';

    if ($authorDate !== null && $authorDate !== '') {
        $env = 'GIT_AUTHOR_DATE='.escapeshellarg($authorDate).' GIT_COMMITTER_DATE='.escapeshellarg($authorDate).' ';
    }

    shell_exec($env.'git -C '.escapeshellarg($root)
        .' -c user.name='.escapeshellarg($authorName)
        .' -c user.email='.escapeshellarg($authorEmail)
        .' commit -m '.escapeshellarg($commitMessage).' 2>/dev/null');

    return trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD 2>/dev/null'));
}

/**
 * @param  array<string, string>  $files
 */
function addMockup(string $code = 'US-001', array $files = []): void
{
    $mockupDir = base_path('.larapilot/mockups/'.$code);
    mkdir($mockupDir, 0755, true);

    foreach ($files as $name => $contents) {
        $path = $mockupDir.'/'.$name;

        if (str_contains($name, '/')) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);
    }
}
