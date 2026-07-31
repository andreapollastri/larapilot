<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ConfigService;
use Larapilot\Services\FrontendService;
use Larapilot\Services\PrdService;

it('persists the frontend repo path via frontend-set', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    $feRoot = sys_get_temp_dir().'/larapilot-fe-'.uniqid();
    mkdir($feRoot, 0755, true);
    file_put_contents($feRoot.'/package.json', json_encode([
        'name' => 'acme-web',
        'dependencies' => ['react' => '^19.0.0'],
    ]));

    expect(Artisan::call('larapilot:frontend-set', [
        '--path' => $feRoot,
        '--stack' => 'React',
    ]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'] ?? null)->toBe('frontend-set')
        ->and($envelope['data']['frontend']['repo_path'] ?? null)->toBe($feRoot)
        ->and($envelope['data']['frontend']['stack'] ?? null)->toBe('React')
        ->and($envelope['data']['frontend']['configured'] ?? null)->toBeTrue();
});

it('rejects an invalid frontend repo path', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    expect(Artisan::call('larapilot:frontend-set', [
        '--path' => '/path/that/does/not/exist-'.uniqid(),
    ]))->toBe(2);
});

it('scans a frontend repository and detects react', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $feRoot = sys_get_temp_dir().'/larapilot-fe-scan-'.uniqid();
    mkdir($feRoot.'/src', 0755, true);
    file_put_contents($feRoot.'/package.json', json_encode([
        'name' => 'scan-app',
        'dependencies' => ['react' => '^19.0.0', 'vite' => '^6.0.0'],
    ]));
    file_put_contents($feRoot.'/vite.config.ts', "export default {}\n");
    file_put_contents($feRoot.'/src/main.tsx', "console.log('ok');\n");

    expect(Artisan::call('larapilot:frontend-scan', ['--path' => $feRoot]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'] ?? null)->toBe('frontend-scan')
        ->and($envelope['data']['ok'] ?? null)->toBeTrue()
        ->and($envelope['data']['stack']['detected'] ?? null)->toBe('React')
        ->and($envelope['data']['tooling']['vite'] ?? null)->toBeTrue()
        ->and($envelope['data']['structure']['src'] ?? null)->toBeTrue();
});

it('syncs the companion bundle into the configured frontend repo', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();
    $config->ensureDirectories();

    app(PrdService::class)->write(validPrd()."\n\n**Frontend Topology:** API + external frontend\n");

    $feRoot = sys_get_temp_dir().'/larapilot-fe-sync-'.uniqid();
    mkdir($feRoot, 0755, true);

    $config->updateFrontend(['repo_path' => $feRoot, 'stack' => 'React']);

    expect(Artisan::call('larapilot:companion-sync'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'] ?? null)->toBe('companion-sync')
        ->and($envelope['data']['ok'] ?? null)->toBeTrue()
        ->and(is_file($feRoot.'/.larapilot/docs/PRD.md'))->toBeTrue()
        ->and(is_file($feRoot.'/.larapilot/companion-sync.md'))->toBeTrue()
        ->and(file_get_contents($feRoot.'/.larapilot/docs/PRD.md'))->toContain('Elevator Pitch');
});

it('exposes frontend config on config-show', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $feRoot = sys_get_temp_dir().'/larapilot-fe-cfg-'.uniqid();
    mkdir($feRoot, 0755, true);
    $config->updateFrontend(['repo_path' => $feRoot, 'stack' => 'Vue']);

    expect(Artisan::call('larapilot:config-show'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['frontend']['repo_path'] ?? null)->toBe($feRoot)
        ->and($envelope['data']['frontend']['stack'] ?? null)->toBe('Vue')
        ->and($envelope['data']['frontend']['configured'] ?? null)->toBeTrue();
});

it('validates frontend path via FrontendService', function (): void {
    $service = app(FrontendService::class);

    $result = $service->validatePath('/definitely/missing/'.uniqid());

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});
