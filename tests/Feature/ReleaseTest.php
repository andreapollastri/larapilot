<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\ReleaseService;
use Larapilot\Support\EnvWriter;

it('persists release_mode and project_docs settings', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $settings = $config->updateSettings([
        'release_mode' => true,
        'project_docs' => true,
    ]);

    expect($settings['release_mode'])->toBe('YES')
        ->and($settings['project_docs'])->toBe('YES');
});

it('adds and lists releases when release mode is enabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'YES'])->assertSuccessful();

    $releases = app(ReleaseService::class);
    $entry = $releases->add('1.0.0', 'Initial release', 'planned', ['US-001']);

    expect($entry['version'])->toBe('1.0.0')
        ->and($entry['branch'])->toBe('release/1.0.0')
        ->and($releases->find('1.0.0')['specs'])->toBe(['US-001']);

    $this->artisan('larapilot:release-list')
        ->assertSuccessful()
        ->expectsOutputToContain('"kind":"release_list"');
});

it('rejects release commands when release mode is disabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'NO'])->assertSuccessful();

    $this->artisan('larapilot:release-add', [
        '--semver' => '1.0.0',
        '--title' => 'Test',
    ])
        ->assertExitCode(4)
        ->expectsOutputToContain('E_PRECONDITION');
});

it('stores frontend repo path in env not yaml', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $config = app(ConfigService::class);

    $path = base_path();
    $saved = $config->updateFrontend([
        'repo_path' => $path,
        'stack' => 'React',
    ]);

    expect($saved['repo_path'])->toBe($path)
        ->and($saved['stack'])->toBe('React')
        ->and(EnvWriter::get('LARAPILOT_FRONTEND_REPO_PATH'))->toBe($path);

    $yaml = file_get_contents($config->configPath());

    expect($yaml)->not->toContain('repo_path: '.$path);
});

it('lists custom skills directory', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $dir = $config->absolutePath('.larapilot/skills/demo/');
    mkdir($dir, 0755, true);
    file_put_contents($dir.'SKILL.md', "---\nname: demo-skill\ndescription: Demo\n---\n\n# Demo\n");

    $this->artisan('larapilot:custom-skill-list')
        ->assertSuccessful()
        ->expectsOutputToContain('demo-skill');
});
