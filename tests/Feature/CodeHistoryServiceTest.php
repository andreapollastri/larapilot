<?php

declare(strict_types=1);

use Larapilot\Services\CodeHistoryService;
use Larapilot\Services\ConfigService;

/**
 * Commit a fresh, uniquely named file and return its basename. Unique names
 * keep each test independent of leftover files in the testbench working tree.
 */
function commitFixtureFile(string $subject, string $body = "<?php\n\nreturn 1;\n"): string
{
    $root = base_path();
    $name = 'code-history-'.bin2hex(random_bytes(6)).'.php';

    file_put_contents($root.'/'.$name, $body);
    shell_exec('git -C '.escapeshellarg($root).' add '.escapeshellarg($name).' 2>/dev/null');
    shell_exec('git -C '.escapeshellarg($root).' commit -m '.escapeshellarg($subject).' 2>/dev/null');

    return $name;
}

it('records files and line ranges from an explicit commit range', function (): void {
    initTestGitRepository('chore: bootstrap');
    $name = commitFixtureFile('feat(US-009): TASK-02 add fixture');

    $entry = app(CodeHistoryService::class)->log([
        'spec' => 'US-009',
        'task' => 'TASK-02',
        'skill' => 'larapilot-implement',
        'range' => 'HEAD~1..HEAD',
    ]);

    expect($entry['spec'])->toBe('US-009')
        ->and($entry['task'])->toBe('TASK-02')
        ->and($entry['range'])->toBe('HEAD~1..HEAD')
        ->and($entry['totals']['files'])->toBeGreaterThanOrEqual(1);

    $fixture = collect($entry['files'])->firstWhere('path', $name);

    expect($fixture)->not->toBeNull()
        ->and($fixture['added'])->toBe(3)
        ->and($fixture['hunks'])->toContain('1-3');
});

it('resolves the range from the spec/task git commit when no range is given', function (): void {
    initTestGitRepository('chore: bootstrap');
    commitFixtureFile('feat(US-010): TASK-01 auto resolve', "<?php\n");

    $entry = app(CodeHistoryService::class)->log([
        'spec' => 'US-010',
        'task' => 'TASK-01',
    ]);

    expect($entry['commit'])->not->toBeNull()
        ->and($entry['range'])->toContain('..');
});

it('groups touchpoints per file in the dashboard view', function (): void {
    initTestGitRepository('chore: bootstrap');
    $name = commitFixtureFile('feat: touch', "<?php\n");

    $history = app(CodeHistoryService::class);
    $history->log(['spec' => 'US-011', 'range' => 'HEAD~1..HEAD']);

    $dashboard = $history->dashboard();

    expect($dashboard['entry_count'])->toBe(1)
        ->and(collect($dashboard['by_file'])->pluck('path'))->toContain($name);
});

it('exposes code_history defaults through settings and honors the toggle', function (): void {
    $config = app(ConfigService::class);

    expect($config->codeHistoryEnabled())->toBeFalse();

    $config->updateSettings(['code_history' => 'YES']);

    expect($config->codeHistoryEnabled())->toBeTrue();
});
