<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\BackstageService;
use Larapilot\Services\PrdService;
use Symfony\Component\Yaml\Yaml;

/**
 * The catalog descriptor and MkDocs config land in the project root, which
 * under Testbench is the shared skeleton app — clean them around every test.
 */
function clearBackstageArtifacts(): void
{
    foreach ([base_path('catalog-info.yaml'), base_path('mkdocs.yml'), base_path('openapi.json')] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

beforeEach(fn () => clearBackstageArtifacts());
afterEach(fn () => clearBackstageArtifacts());

it('exports a backstage bundle via artisan', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    expect(Artisan::call('larapilot:backstage-export'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope)->toBeArray()
        ->and($envelope['kind'] ?? null)->toBe('backstage-export')
        ->and($envelope['data']['source'] ?? null)->toBe('larapilot')
        ->and($envelope['data']['catalog']['entities'][0]['kind'] ?? null)->toBe('Component')
        ->and($envelope['data']['catalog']['entities'][0]['apiVersion'] ?? null)->toBe('backstage.io/v1alpha1')
        ->and($envelope['data']['snapshot']['stories'][0]['code'] ?? null)->toBe('US-001')
        ->and($envelope['data']['catalog']['yaml'] ?? '')->toContain('kind: Component');
});

it('names the component from the app name and honors owner, system, and lifecycle', function (): void {
    config()->set('app.name', 'Checkout Service');
    config()->set('larapilot.backstage.owner', 'group:default/payments');
    config()->set('larapilot.backstage.system', 'commerce');
    config()->set('larapilot.backstage.lifecycle', 'production');

    $entities = app(BackstageService::class)->entities();
    $component = $entities[0];

    expect($component['metadata']['name'])->toBe('checkout-service')
        ->and($component['metadata']['namespace'])->toBe('default')
        ->and($component['metadata']['title'])->toBe('Checkout Service')
        ->and($component['spec']['owner'])->toBe('group:default/payments')
        ->and($component['spec']['system'])->toBe('commerce')
        ->and($component['spec']['lifecycle'])->toBe('production')
        ->and($component['spec']['type'])->toBe('service')
        ->and($component['metadata']['annotations']['backstage.io/techdocs-ref'])->toBe('dir:.')
        ->and(app(BackstageService::class)->entityRef())->toBe('component:default/checkout-service');
});

it('describes the component from the PRD elevator pitch', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    app(PrdService::class)->write(validPrd());

    $component = app(BackstageService::class)->entities()[0];

    expect($component['metadata']['description'])->toBe('A thing.')
        ->and($component['metadata']['annotations']['larapilot.io/prd'])->toBe('.larapilot/docs/PRD.md');
});

it('registers the product OpenAPI contract as an API entity', function (): void {
    config()->set('app.name', 'Checkout');
    file_put_contents(base_path('openapi.json'), json_encode(['openapi' => '3.1.0']));

    $entities = app(BackstageService::class)->entities();

    expect($entities)->toHaveCount(2)
        ->and($entities[0]['spec']['providesApis'])->toBe(['checkout-api'])
        ->and($entities[1]['kind'])->toBe('API')
        ->and($entities[1]['metadata']['name'])->toBe('checkout-api')
        ->and($entities[1]['spec']['type'])->toBe('openapi')
        ->and($entities[1]['spec']['definition'])->toBe(['$text' => './openapi.json']);
});

it('registers the larapilot workflow API only when explicitly enabled', function (): void {
    config()->set('app.name', 'Checkout');

    expect(app(BackstageService::class)->entities())->toHaveCount(1);

    config()->set('larapilot.backstage.workflow_api', true);

    $entities = app(BackstageService::class)->entities();

    expect($entities)->toHaveCount(2)
        ->and($entities[1]['metadata']['name'])->toBe('checkout-larapilot-workflow')
        ->and($entities[1]['spec']['definition'])->toBe([
            '$text' => './.larapilot/backstage/larapilot-openapi.json',
        ]);
});

it('writes the catalog descriptor, mkdocs config, and techdocs sources', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    app(PrdService::class)->write(validPrd());
    addSpec();
    planSpec();

    expect(Artisan::call('larapilot:backstage-export', ['--write' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['catalog']['written'] ?? null)->toBeTrue()
        ->and($envelope['data']['catalog']['path'] ?? null)->toBe('catalog-info.yaml')
        ->and(is_file(base_path('catalog-info.yaml')))->toBeTrue()
        ->and(is_file(base_path('mkdocs.yml')))->toBeTrue()
        ->and(is_file(base_path('.larapilot/techdocs/index.md')))->toBeTrue()
        ->and(is_file(base_path('.larapilot/techdocs/prd.md')))->toBeTrue()
        ->and(is_file(base_path('.larapilot/techdocs/backlog/index.md')))->toBeTrue()
        ->and(is_file(base_path('.larapilot/techdocs/backlog/US-001.md')))->toBeTrue();

    $mkdocs = Yaml::parseFile(base_path('mkdocs.yml'));

    expect($mkdocs['docs_dir'])->toBe('.larapilot/techdocs')
        ->and($mkdocs['plugins'])->toBe(['techdocs-core'])
        ->and($mkdocs['nav'][0])->toBe(['Overview' => 'index.md'])
        ->and($mkdocs['nav'][2]['Backlog'])->toContain(['US-001 — Login' => 'backlog/US-001.md']);

    $story = (string) file_get_contents(base_path('.larapilot/techdocs/backlog/US-001.md'));

    expect($story)->toContain('# US-001 — Login')
        ->and($story)->toContain('I want to log in')
        ->and($story)->toContain('## Technical plan')
        ->and($story)->toContain('- [ ] **TASK-01** — Create model');

    expect((string) file_get_contents(base_path('.larapilot/techdocs/prd.md')))->toContain('Elevator Pitch');
});

it('keeps an existing catalog descriptor unless forced', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    file_put_contents(base_path('catalog-info.yaml'), "# hand written\n");

    expect(Artisan::call('larapilot:backstage-export', ['--write' => true]))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['catalog']['skipped'] ?? null)->toBeTrue()
        ->and($envelope['data']['catalog']['reason'] ?? null)->toBe('exists')
        ->and($envelope['data']['hint'] ?? null)->toContain('--force')
        ->and((string) file_get_contents(base_path('catalog-info.yaml')))->toBe("# hand written\n");

    expect(Artisan::call('larapilot:backstage-export', ['--write' => true, '--force' => true]))->toBe(0);

    expect((string) file_get_contents(base_path('catalog-info.yaml')))->toContain('kind: Component');
});

it('prunes techdocs pages for specs that no longer exist', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addSpec(['code' => 'US-002', 'title' => 'Logout']);

    expect(Artisan::call('larapilot:backstage-export', ['--write' => true]))->toBe(0);
    expect(is_file(base_path('.larapilot/techdocs/backlog/US-002.md')))->toBeTrue();

    $this->artisan('larapilot:spec-delete', ['code' => 'US-002'])->assertSuccessful();

    expect(Artisan::call('larapilot:backstage-export', ['--write' => true, '--force' => true]))->toBe(0);

    expect(is_file(base_path('.larapilot/techdocs/backlog/US-002.md')))->toBeFalse()
        ->and(is_file(base_path('.larapilot/techdocs/backlog/US-001.md')))->toBeTrue();
});

it('never derives a techdocs page path from an unsafe spec code', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    // A hand-edited backlog.yaml bypasses the spec-add guard, and page paths
    // are derived from the code — so generation re-validates it.
    file_put_contents(base_path('.larapilot/backlog.yaml'), Yaml::dump([
        'specs' => [
            ['code' => 'US-001', 'title' => 'Login', 'status' => 'TODO', 'body' => validSpecBody()],
            ['code' => '../../escaped', 'title' => 'Escape', 'status' => 'TODO', 'body' => 'nope'],
        ],
    ], 4, 2));

    $pages = app(BackstageService::class)->techdocsPageNames();

    expect($pages)->toContain('backlog/US-001.md')
        ->and($pages)->not->toContain('backlog/../../escaped.md');
});

it('skips techdocs generation with --no-techdocs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    expect(Artisan::call('larapilot:backstage-export', ['--write' => true, '--no-techdocs' => true]))->toBe(0);

    expect(is_file(base_path('catalog-info.yaml')))->toBeTrue()
        ->and(is_file(base_path('mkdocs.yml')))->toBeFalse()
        ->and(is_dir(base_path('.larapilot/techdocs')))->toBeFalse();
});

it('reports blocking feedback and task progress in the snapshot', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    planSpec();

    $this->artisan('larapilot:spec-comment', [
        'code' => 'US-001',
        '--author' => 'PM',
        '--message' => 'Confirm the SSO scope.',
        '--blocks-merge' => true,
    ])->assertSuccessful();

    $snapshot = app(BackstageService::class)->snapshot();

    expect($snapshot['blocking_feedback']['count'])->toBe(1)
        ->and($snapshot['blocking_feedback']['specs'])->toBe(['US-001'])
        ->and($snapshot['stories'][0]['blocking_feedback'])->toBe(1)
        ->and($snapshot['stories'][0]['tasks'])->toBe(['total' => 2, 'done' => 0])
        ->and($snapshot['counts_by_status']['PLANNED'])->toBe(1)
        ->and($snapshot['metrics']['total'])->toBe(1);
});

it('serves the backstage bundle over the API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->getJson('/larapilot/api/backstage')
        ->assertOk()
        ->assertJsonStructure([
            'generated_at',
            'source',
            'version',
            'catalog' => ['path', 'entity_refs', 'entities', 'yaml'],
            'techdocs' => ['enabled', 'docs_dir', 'pages'],
            'snapshot' => ['entity_ref', 'metrics', 'counts_by_status', 'stories', 'links'],
            'instructions',
        ])
        ->assertJsonPath('snapshot.links.board', 'http://localhost/larapilot')
        ->assertJsonPath('snapshot.links.api', 'http://localhost/larapilot/api')
        ->assertJsonPath('snapshot.stories.0.code', 'US-001');
});

it('serves the catalog descriptor as yaml', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $response = $this->get('/larapilot/api/backstage/catalog-info.yaml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/yaml');

    $parsed = Yaml::parse($response->getContent() ?: '');

    expect($parsed['kind'] ?? null)->toBe('Component')
        ->and($parsed['metadata']['annotations']['larapilot.io/board-url'] ?? null)
        ->toBe('http://localhost/larapilot');
});

it('hides the backstage endpoints when the integration is disabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    config()->set('larapilot.backstage.enabled', false);

    $this->getJson('/larapilot/api/backstage')->assertNotFound();
    $this->get('/larapilot/api/backstage/catalog-info.yaml')->assertNotFound();

    expect(Artisan::call('larapilot:backstage-export'))->toBe(4);
});

it('exposes backstage configuration on config-show', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    config()->set('app.name', 'Checkout');

    expect(Artisan::call('larapilot:config-show'))->toBe(0);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['backstage']['enabled'] ?? null)->toBeTrue()
        ->and($envelope['data']['backstage']['entity_ref'] ?? null)->toBe('component:default/checkout')
        ->and($envelope['data']['backstage']['catalog_path'] ?? null)->toBe('catalog-info.yaml')
        ->and($envelope['data']['backstage']['catalog_exists'] ?? null)->toBeFalse()
        ->and($envelope['data']['backstage']['techdocs']['docs_dir'] ?? null)->toBe('.larapilot/techdocs');
});

it('writes the bundle to a file with --file', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $path = base_path('.larapilot/backstage-bundle.json');

    expect(Artisan::call('larapilot:backstage-export', ['--file' => $path]))->toBe(0)
        ->and(is_file($path))->toBeTrue();

    $decoded = json_decode((string) file_get_contents($path), true);

    expect($decoded['source'] ?? null)->toBe('larapilot')
        ->and($decoded['skill'] ?? null)->toBe('larapilot-backstage')
        ->and($decoded['catalog']['entity_refs'][0] ?? null)->toStartWith('component:default/');
});
