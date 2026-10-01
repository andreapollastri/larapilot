<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ConfigService;
use Symfony\Component\Yaml\Yaml;

function handbookStub(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/larapilot/handbook/README.md');
}

function legacyProjectDocs(array $files): void
{
    foreach ($files as $relative => $content) {
        $path = base_path('_project_docs/'.$relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
    }
}

function pointConfigAtLegacyProjectDocs(): void
{
    $config = Yaml::parseFile(base_path('.larapilot/config.yaml'));
    $config['paths']['project_docs'] = '_project_docs/';

    file_put_contents(base_path('.larapilot/config.yaml'), Yaml::dump($config, 4, 2));

    app()->forgetInstance(ConfigService::class);
}

it('scaffolds the handbook under .larapilot/docs on install, never _project_docs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(base_path('.larapilot/docs/handbook/README.md'))->toBeFile()
        ->and(base_path('.larapilot/docs/handbook/.gitkeep'))->toBeFile()
        ->and(file_get_contents(base_path('.larapilot/docs/handbook/README.md')))->toBe(handbookStub())
        ->and(is_dir(base_path('_project_docs')))->toBeFalse()
        ->and(Yaml::parseFile(base_path('.larapilot/config.yaml'))['paths']['project_docs'])->toBe('.larapilot/docs/handbook/')
        ->and(app(ConfigService::class)->setupInfo()['paths']['project_docs'])->toEndWith('.larapilot/docs/handbook/');
});

it('never overwrites the handbook readme once the handbook index replaced it', function (): void {
    $config = app(ConfigService::class);
    $config->ensureDirectories();

    file_put_contents(base_path('.larapilot/docs/handbook/README.md'), '# Handbook index');

    $config->ensureDirectories();

    expect(file_get_contents(base_path('.larapilot/docs/handbook/README.md')))->toBe('# Handbook index');
});

it('reads a config that still names _project_docs as the handbook', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    pointConfigAtLegacyProjectDocs();

    $config = app(ConfigService::class);
    $config->ensureDirectories();

    expect($config->setupInfo()['paths']['project_docs'])->toEndWith('.larapilot/docs/handbook/')
        ->and(is_dir(base_path('_project_docs')))->toBeFalse();
});

it('moves a legacy _project_docs folder into .larapilot/docs/handbook on update', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    pointConfigAtLegacyProjectDocs();
    legacyProjectDocs([
        '.gitkeep' => '',
        'README.md' => '# Handbook index',
        '01-overview.md' => '# Overview',
        '03-features/billing.md' => '# Billing',
        '03-features/.gitkeep' => '',
        'diagrams/flow.mmd' => 'flowchart LR',
    ]);

    $this->artisan('larapilot:update', ['--skip-boost' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Handbook moved: 4 file(s) from _project_docs/ to .larapilot/docs/handbook/.')
        ->expectsOutputToContain('Removed the empty _project_docs/ folder.');

    expect(is_dir(base_path('_project_docs')))->toBeFalse()
        ->and(file_get_contents(base_path('.larapilot/docs/handbook/README.md')))->toBe('# Handbook index')
        ->and(file_get_contents(base_path('.larapilot/docs/handbook/01-overview.md')))->toBe('# Overview')
        ->and(file_get_contents(base_path('.larapilot/docs/handbook/03-features/billing.md')))->toBe('# Billing')
        ->and(file_get_contents(base_path('.larapilot/docs/handbook/diagrams/flow.mmd')))->toBe('flowchart LR')
        ->and(base_path('.larapilot/docs/handbook/.gitkeep'))->toBeFile()
        ->and(Yaml::parseFile(base_path('.larapilot/config.yaml'))['paths']['project_docs'])->toBe('.larapilot/docs/handbook/');
});

it('leaves behind the legacy files the handbook already holds', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    file_put_contents(base_path('.larapilot/docs/handbook/README.md'), '# New index');
    file_put_contents(base_path('.larapilot/docs/handbook/01-overview.md'), '# New overview');
    legacyProjectDocs([
        'README.md' => '# Old index',
        '01-overview.md' => '# Old overview',
        '02-architecture.md' => '# Architecture',
    ]);

    $this->artisan('larapilot:update', ['--skip-boost' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Handbook moved: 1 file(s)')
        ->expectsOutputToContain('01-overview.md, README.md');

    expect(file_get_contents(base_path('.larapilot/docs/handbook/README.md')))->toBe('# New index')
        ->and(file_get_contents(base_path('.larapilot/docs/handbook/01-overview.md')))->toBe('# New overview')
        ->and(file_get_contents(base_path('.larapilot/docs/handbook/02-architecture.md')))->toBe('# Architecture')
        ->and(file_get_contents(base_path('_project_docs/README.md')))->toBe('# Old index')
        ->and(file_get_contents(base_path('_project_docs/01-overview.md')))->toBe('# Old overview')
        ->and(base_path('_project_docs/02-architecture.md'))->not->toBeFile();
});

it('removes the empty _project_docs folder an older install left behind', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    legacyProjectDocs(['.gitkeep' => '']);

    $migration = app(ConfigService::class)->migrateLegacyProjectDocs();

    expect($migration)->toBe([
        'from' => '_project_docs/',
        'to' => '.larapilot/docs/handbook/',
        'moved' => [],
        'kept' => [],
        'removed' => true,
    ])
        ->and(is_dir(base_path('_project_docs')))->toBeFalse()
        ->and(file_get_contents(base_path('.larapilot/docs/handbook/README.md')))->toBe(handbookStub());
});

it('reports a leftover _project_docs folder on doctor', function (): void {
    expect(Artisan::call('larapilot:install'))->toBe(0);
    expect(Artisan::call('larapilot:doctor'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['checks']['handbook_scaffold'])->toBeTrue()
        ->and($envelope['data']['legacy_project_docs'])->toBeFalse();

    legacyProjectDocs(['01-overview.md' => '# Overview']);

    expect(Artisan::call('larapilot:doctor'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['legacy_project_docs'])->toBeTrue();
});
