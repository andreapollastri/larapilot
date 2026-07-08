<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Services\ValidationService;
use Larapilot\Support\AtomicFile;
use Larapilot\Support\SpecCode;

it('rejects spec codes that could escape the data directory', function (): void {
    expect(SpecCode::isValid('US-001'))->toBeTrue()
        ->and(SpecCode::isValid('epic.US_1-a'))->toBeTrue()
        ->and(SpecCode::isValid('../evil'))->toBeFalse()
        ->and(SpecCode::isValid('US-001/../..'))->toBeFalse()
        ->and(SpecCode::isValid(''))->toBeFalse();

    app(SpecService::class)->specPath('../evil');
})->throws(RuntimeException::class);

it('skips specs with unsafe codes when adding', function (): void {
    $specs = app(SpecService::class);
    $specs->add([
        ['code' => '../evil', 'title' => 'Nope', 'body' => validSpecBody()],
        ['code' => 'US-001', 'title' => 'Ok', 'body' => validSpecBody()],
    ]);

    expect($specs->list()['summary']['codes'])->toBe(['US-001']);
});

it('appends status history and resets rework when leaving review', function (): void {
    $specs = app(SpecService::class);
    $specs->add(specsPayload(['rework' => true, 'status' => 'REVIEW'])['specs']);

    $specs->setStatus('US-001', 'IN PROGRESS');

    $spec = $specs->find('US-001');

    expect($spec['rework'])->toBeFalse()
        ->and($spec['status_history'])->toHaveCount(1)
        ->and($spec['status_history'][0]['status'])->toBe('IN PROGRESS');
});

it('merges updates without losing the spec code', function (): void {
    $specs = app(SpecService::class);
    $specs->add(specsPayload()['specs']);

    $specs->update('US-001', ['title' => 'Renamed', 'code' => 'HACKED']);

    expect($specs->find('US-001')['title'])->toBe('Renamed')
        ->and($specs->find('HACKED'))->toBeNull();
});

it('resolves absolute and relative paths', function (): void {
    $config = app(ConfigService::class);

    expect($config->absolutePath('/already/absolute'))->toBe('/already/absolute')
        ->and($config->absolutePath('C:\\win\\path'))->toBe('C:\\win\\path')
        ->and($config->absolutePath('.larapilot/x'))->toBe(rtrim(base_path(), '/').'/.larapilot/x')
        ->and($config->relativePath(base_path('.larapilot/x')))->toBe('.larapilot/x');
});

it('memoizes project config and refreshes it after writes', function (): void {
    $config = app(ConfigService::class);

    expect($config->resolve()['connector'])->toBe('file');

    $config->writeProjectConfig(['connector' => 'custom']);

    expect($config->resolve()['connector'])->toBe('custom');
});

it('requires marked-up sections in spec bodies', function (): void {
    $validation = app(ValidationService::class);

    $prose = specsPayload(['body' => 'This mentions User Story, Demonstrates and Acceptance Criteria in prose.']);
    expect($validation->validateSpecPayload($prose)['ok'])->toBeFalse();

    $headings = specsPayload(['body' => "## User Story\nx\n\n## Demonstrates\nx\n\n## Acceptance Criteria\n- [ ] x"]);
    expect($validation->validateSpecPayload($headings)['ok'])->toBeTrue();

    $italian = specsPayload(['body' => "**Storia Utente**\nx\n\n**Dimostra**\nx\n\n**Criteri di Accettazione**\n- [ ] x"]);
    expect($validation->validateSpecPayload($italian)['ok'])->toBeTrue();
});

it('flags invalid spec codes during validation', function (): void {
    $validation = app(ValidationService::class);

    $result = $validation->validateSpecPayload(specsPayload(['code' => '../evil']));

    expect($result['ok'])->toBeFalse()
        ->and(array_column($result['findings'], 'code'))->toContain('SPEC_INVALID_CODE');

    $plan = $validation->validatePlanPayload('../evil', planPayload());

    expect(array_column($plan['findings'], 'code'))->toContain('PLAN_INVALID_CODE');
});

it('writes files atomically, creating parent directories', function (): void {
    $path = base_path('.larapilot/deep/nested/file.txt');

    AtomicFile::write($path, 'hello');

    expect(file_get_contents($path))->toBe('hello');

    AtomicFile::write($path, 'replaced');

    expect(file_get_contents($path))->toBe('replaced')
        ->and(glob(dirname($path).'/.*.tmp'))->toBe([]);
});
