<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Larapilot\Services\Sbom\NodeLockfiles;
use Larapilot\Services\SbomService;

const SBOM_FIXTURE_FILES = ['composer.json', 'composer.lock', 'package.json', 'package-lock.json'];

beforeEach(function (): void {
    $this->sbomBackup = [];

    foreach (SBOM_FIXTURE_FILES as $file) {
        $path = base_path($file);
        $this->sbomBackup[$file] = is_file($path) ? (string) file_get_contents($path) : null;
    }

    $this->sbomFolders = [];
});

afterEach(function (): void {
    foreach ($this->sbomBackup as $file => $contents) {
        $path = base_path($file);

        if ($contents === null) {
            if (is_file($path)) {
                unlink($path);
            }
        } else {
            file_put_contents($path, $contents);
        }
    }

    foreach ($this->sbomFolders as $folder) {
        shell_exec('rm -rf '.escapeshellarg($folder));
    }
});

/**
 * A Laravel project with a vulnerable transitive Composer package and a
 * frontend with one vulnerable direct npm package.
 */
function sbomProject(): void
{
    file_put_contents(base_path('composer.json'), json_encode([
        'name' => 'acme/shop',
        'require' => ['php' => '^8.2', 'laravel/framework' => '^12.0'],
        'require-dev' => ['pestphp/pest' => '^3.0'],
    ], JSON_PRETTY_PRINT));

    file_put_contents(base_path('composer.lock'), json_encode([
        'packages' => [
            ['name' => 'laravel/framework', 'version' => 'v12.10.0', 'license' => ['MIT'], 'type' => 'library', 'require' => ['php' => '^8.2', 'guzzlehttp/psr7' => '^2.0']],
            ['name' => 'guzzlehttp/psr7', 'version' => '2.4.0', 'license' => ['MIT'], 'type' => 'library', 'require' => ['php' => '^8.0']],
            ['name' => 'old/thing', 'version' => '1.0.0', 'license' => ['GPL-3.0-only'], 'type' => 'library', 'abandoned' => 'new/thing'],
        ],
        'packages-dev' => [
            ['name' => 'pestphp/pest', 'version' => 'v3.0.0', 'license' => ['MIT'], 'type' => 'library'],
        ],
    ], JSON_PRETTY_PRINT));

    file_put_contents(base_path('package.json'), json_encode([
        'private' => true,
        'dependencies' => ['axios' => '^1.6.0'],
        'devDependencies' => ['vite' => '^5.0.0', '@inertiajs/vue3' => '^2.0.0'],
    ], JSON_PRETTY_PRINT));

    file_put_contents(base_path('package-lock.json'), json_encode([
        'name' => 'shop',
        'lockfileVersion' => 3,
        'packages' => [
            '' => ['dependencies' => ['axios' => '^1.6.0'], 'devDependencies' => ['vite' => '^5.0.0']],
            'node_modules/axios' => ['version' => '1.6.0', 'license' => 'MIT'],
            'node_modules/follow-redirects' => ['version' => '1.15.2', 'license' => 'MIT'],
            'node_modules/vite' => ['version' => '5.0.0', 'dev' => true, 'license' => 'MIT'],
            'node_modules/@inertiajs/vue3' => ['version' => '2.0.1', 'dev' => true, 'license' => 'MIT'],
            'node_modules/vite/node_modules/esbuild' => ['version' => '0.19.0', 'dev' => true, 'license' => 'MIT'],
            'packages/ui' => ['version' => '0.0.0'],
            'node_modules/ui' => ['link' => true, 'resolved' => 'packages/ui'],
        ],
    ], JSON_PRETTY_PRINT));
}

function fakeOsv(): void
{
    Http::fake([
        'api.osv.dev/v1/querybatch' => function ($request) {
            $results = [];

            foreach ($request['queries'] as $query) {
                $results[] = match ($query['package']['name']) {
                    'guzzlehttp/psr7' => ['vulns' => [['id' => 'GHSA-wxmh-65f7-jcvw', 'modified' => '2026-02-04T04:19:33Z']]],
                    'axios' => ['vulns' => [['id' => 'GHSA-axio-0001-high', 'modified' => '2026-09-30T00:00:00Z'], ['id' => 'GHSA-axio-0002-crit', 'modified' => '2026-09-30T00:00:00Z']]],
                    default => (object) [],
                };
            }

            return Http::response(['results' => $results]);
        },
        'api.osv.dev/v1/vulns/GHSA-wxmh-65f7-jcvw' => Http::response([
            'id' => 'GHSA-wxmh-65f7-jcvw',
            'summary' => 'Improper header name validation in guzzlehttp/psr7',
            'aliases' => ['CVE-2023-29197'],
            'modified' => '2026-02-04T04:19:33Z',
            'database_specific' => ['severity' => 'MODERATE'],
            'affected' => [
                ['package' => ['name' => 'guzzlehttp/psr7', 'ecosystem' => 'Packagist'], 'ranges' => [['type' => 'ECOSYSTEM', 'events' => [['introduced' => '0'], ['fixed' => '1.9.1']]]]],
                ['package' => ['name' => 'guzzlehttp/psr7', 'ecosystem' => 'Packagist'], 'ranges' => [['type' => 'ECOSYSTEM', 'events' => [['introduced' => '2.0.0'], ['fixed' => '2.4.5']]]]],
            ],
        ]),
        'api.osv.dev/v1/vulns/GHSA-axio-0001-high' => Http::response([
            'id' => 'GHSA-axio-0001-high',
            'summary' => 'Axios: SSRF through absolute URLs',
            'aliases' => ['CVE-2026-0001'],
            'modified' => '2026-09-30T00:00:00Z',
            'database_specific' => ['severity' => 'HIGH'],
            'affected' => [['package' => ['name' => 'axios', 'ecosystem' => 'npm'], 'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '1.0.0'], ['fixed' => '1.8.2']]]]]],
        ]),
        // No severity word: the CVSS vector decides.
        'api.osv.dev/v1/vulns/GHSA-axio-0002-crit' => Http::response([
            'id' => 'GHSA-axio-0002-crit',
            'details' => 'Axios: prototype pollution in the form serializer.',
            'modified' => '2026-09-30T00:00:00Z',
            'severity' => [['type' => 'CVSS_V3', 'score' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H']],
            'affected' => [['package' => ['name' => 'axios', 'ecosystem' => 'npm'], 'ranges' => [['type' => 'SEMVER', 'events' => [['introduced' => '1.0.0'], ['fixed' => '1.7.4']]]]]],
        ]),
    ]);
}

/**
 * @param  array<string, mixed>  $parameters
 * @return array{0: int, 1: array<string, mixed>}
 */
function vendorAudit(array $parameters = []): array
{
    $exit = Artisan::call('larapilot:vendor-audit', $parameters);

    return [$exit, json_decode(Artisan::output(), true)];
}

it('lists every package of the lockfiles with its scope, license, and package URL', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    sbomProject();

    $inventory = app(SbomService::class)->inventory();
    $components = collect($inventory['components'])->keyBy(fn (array $component): string => $component['inventory'].':'.$component['name']);

    expect(array_column($inventory['inventories'], 'id'))->toBe(['composer', 'assets'])
        ->and($inventory['totals'])->toMatchArray(['components' => 9, 'abandoned' => 1])
        ->and($components['composer:laravel/framework'])->toMatchArray(['version' => '12.10.0', 'scope' => 'prod', 'direct' => true, 'constraint' => '^12.0', 'purl' => 'pkg:composer/laravel/framework@12.10.0'])
        ->and($components['composer:guzzlehttp/psr7'])->toMatchArray(['direct' => false, 'scope' => 'prod'])
        ->and($components['composer:pestphp/pest']['scope'])->toBe('dev')
        // Hoisted transitive packages are not direct; package.json decides.
        ->and($components['assets:axios'])->toMatchArray(['direct' => true, 'scope' => 'prod', 'constraint' => '^1.6.0'])
        ->and($components['assets:follow-redirects']['direct'])->toBeFalse()
        ->and($components['assets:@inertiajs/vue3']['purl'])->toBe('pkg:npm/%40inertiajs/vue3@2.0.1')
        // Workspace links are not dependencies.
        ->and($components->has('assets:ui'))->toBeFalse()
        ->and($inventory['licenses']['classes'])->toMatchArray(['strong-copyleft' => 1])
        ->and($inventory['licenses']['copyleft'][0]['name'])->toBe('old/thing');
});

it('reads pnpm, Yarn, and Bun lockfiles', function (): void {
    $root = sys_get_temp_dir().'/larapilot-sbom-'.bin2hex(random_bytes(6));
    $this->sbomFolders[] = $root;
    mkdir($root, 0755, true);
    $reader = new NodeLockfiles;

    file_put_contents($root.'/package.json', json_encode(['dependencies' => ['vue' => '^3.4.0', '@vue/shared' => '^3.4.0']]));

    file_put_contents($root.'/pnpm-lock.yaml', "lockfileVersion: '9.0'\nimporters:\n  .:\n    dependencies:\n      vue:\n        specifier: ^3.4.0\n        version: 3.4.21\npackages:\n  vue@3.4.21:\n    resolution: {integrity: sha512-x}\n  '@vue/shared@3.4.21':\n    resolution: {integrity: sha512-y}\n  string_decoder@1.3.0:\n    resolution: {integrity: sha512-z}\n  react-dom@18.2.0(react@18.2.0):\n    resolution: {integrity: sha512-w}\n");

    $pnpm = collect($reader->read($root)['packages'])->keyBy('name');

    expect($reader->read($root)['manager'])->toBe('pnpm')
        ->and($pnpm->keys()->all())->toEqualCanonicalizing(['vue', '@vue/shared', 'string_decoder', 'react-dom'])
        ->and($pnpm['vue'])->toMatchArray(['version' => '3.4.21', 'direct' => true])
        ->and($pnpm['react-dom']['version'])->toBe('18.2.0')
        ->and(NodeLockfiles::pnpmKey('/@babel/core/7.12.3_supports-color@5.5.0'))->toBe(['@babel/core', '7.12.3'])
        ->and(NodeLockfiles::pnpmKey('/lodash@4.17.21'))->toBe(['lodash', '4.17.21']);

    unlink($root.'/pnpm-lock.yaml');
    file_put_contents($root.'/yarn.lock', "# yarn lockfile v1\n\n\"@vue/shared@^3.4.0\", \"@vue/shared@3.4.21\":\n  version \"3.4.21\"\n  resolved \"https://registry.yarnpkg.com/@vue/shared/-/shared-3.4.21.tgz\"\n\nvue@^3.4.0:\n  version \"3.4.21\"\n\nstring-width-cjs@npm:string-width@^4.2.0:\n  version \"4.2.3\"\n");

    expect(collect($reader->read($root)['packages'])->pluck('version', 'name')->all())->toBe(['@vue/shared' => '3.4.21', 'string-width' => '4.2.3', 'vue' => '3.4.21']);

    file_put_contents($root.'/yarn.lock', "__metadata:\n  version: 8\n\n\"vue@npm:^3.4.0\":\n  version: 3.4.21\n  resolution: \"vue@npm:3.4.21\"\n\n\"app@workspace:.\":\n  version: 0.0.0-use.local\n");

    expect(collect($reader->read($root)['packages'])->pluck('version', 'name')->all())->toBe(['vue' => '3.4.21']);

    unlink($root.'/yarn.lock');
    file_put_contents($root.'/bun.lock', "{\n  \"lockfileVersion\": 1,\n  \"workspaces\": { \"\": { \"dependencies\": { \"vue\": \"^3.4.0\", }, }, },\n  \"packages\": {\n    \"vue\": [\"vue@3.4.21\", \"\", {}, \"sha512-x\"],\n    \"@vue/shared\": [\"@vue/shared@3.4.21\", \"\", {}, \"sha512-y\"],\n  },\n}\n");

    expect(collect($reader->read($root)['packages'])->pluck('version', 'name')->all())->toBe(['@vue/shared' => '3.4.21', 'vue' => '3.4.21']);
});

it('adds the lockfile of the frontend companion', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    sbomProject();

    $frontend = sys_get_temp_dir().'/larapilot-sbom-fe-'.bin2hex(random_bytes(6));
    $this->sbomFolders[] = $frontend;
    mkdir($frontend, 0755, true);
    file_put_contents($frontend.'/package.json', json_encode(['dependencies' => ['@angular/core' => '^18.0.0']]));
    file_put_contents($frontend.'/package-lock.json', json_encode(['lockfileVersion' => 3, 'packages' => ['' => [], 'node_modules/@angular/core' => ['version' => '18.2.0', 'license' => 'MIT']]]));
    config(['larapilot.frontend.repo_path' => $frontend]);

    $inventory = app(SbomService::class)->inventory();
    $companion = collect($inventory['inventories'])->firstWhere('id', 'companion');

    expect($companion)->toMatchArray(['label' => 'Frontend companion', 'file' => 'package-lock.json', 'count' => 1])
        ->and($companion['where'])->toStartWith(basename($frontend))
        ->and(collect($inventory['components'])->firstWhere('inventory', 'companion')['name'])->toBe('@angular/core');
});

it('checks the packages against OSV.dev and gates on what stays open', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    sbomProject();
    fakeOsv();

    [$exit, $envelope] = vendorAudit(['--report' => true, '--gate' => true]);
    $data = $envelope['data'];
    $findings = collect($data['findings'])->keyBy('id');
    $packages = collect($data['packages'])->keyBy('package');

    expect($exit)->toBe(1)
        ->and($envelope['kind'])->toBe('vendor_audit')
        ->and($data['components'])->toBe(9)
        ->and($data['counts'])->toMatchArray(['critical' => 1, 'high' => 1, 'medium' => 1])
        ->and($findings['GHSA-wxmh-65f7-jcvw'])->toMatchArray(['severity' => 'medium', 'fixed' => '2.4.5', 'aliases' => ['CVE-2023-29197'], 'package' => 'guzzlehttp/psr7'])
        // No severity word: CVSS 9.8.
        ->and($findings['GHSA-axio-0002-crit'])->toMatchArray(['severity' => 'critical', 'score' => 9.8, 'fixed' => '1.7.4'])
        ->and($findings['GHSA-axio-0002-crit']['summary'])->toContain('prototype pollution')
        // The fix of a package is the highest version its advisories name.
        ->and($packages['axios'])->toMatchArray(['severity' => 'critical', 'fixed' => '1.8.2', 'fix' => 'npm update axios'])
        ->and($packages['guzzlehttp/psr7']['fix'])->toBe('composer update guzzlehttp/psr7 --with-dependencies')
        ->and($data['gate']['verdict'])->toBe('FAIL')
        ->and($data['gate']['blocking'])->toEqualCanonicalizing(['GHSA-axio-0001-high', 'GHSA-axio-0002-crit'])
        ->and(base_path($data['report']))->toBeFile()
        ->and((string) file_get_contents(base_path($data['report'])))->toContain('# Vulnerable dependencies', 'GHSA-wxmh-65f7-jcvw', 'npm update axios');

    // The decision of the high and the critical: one waived, one in the backlog.
    $this->artisan('larapilot:vendor-link', ['ids' => 'GHSA-axio-0002-crit', '--waive' => true, '--reason' => 'The form serializer is never fed user input'])->assertSuccessful();
    $this->artisan('larapilot:vendor-link', ['ids' => 'GHSA-axio-0001-high', '--spec' => 'us-012'])->assertSuccessful();

    [$exit, $cached] = vendorAudit(['--cached' => true, '--gate' => true]);
    $states = collect($cached['data']['findings'])->pluck('state', 'id');

    // In the backlog is not fixed: it still stops the gate.
    expect($exit)->toBe(1)
        ->and($states->all())->toMatchArray(['GHSA-axio-0002-crit' => 'waived', 'GHSA-axio-0001-high' => 'in_backlog', 'GHSA-wxmh-65f7-jcvw' => 'open'])
        ->and($cached['data']['counts'])->toMatchArray(['critical' => 0, 'high' => 1])
        ->and($cached['data']['gate']['blocking'])->toBe(['GHSA-axio-0001-high']);

    $this->artisan('larapilot:vendor-link', ['ids' => 'GHSA-axio-0001-high', '--waive' => true, '--reason' => 'Behind the VPN'])->assertSuccessful();

    [$exit, $relaxed] = vendorAudit(['--cached' => true, '--gate' => true]);

    expect($exit)->toBe(0)
        ->and($relaxed['data']['gate']['verdict'])->toBe('WARN')
        ->and(vendorAudit(['--cached' => true, '--new' => true])[1]['data']['findings'])->toHaveCount(1);

    // Decisions are committed; the advisories stay in the cache.
    $ledger = (string) file_get_contents(base_path('.larapilot/vendor-audit.yaml'));

    expect($ledger)->toContain('GHSA-axio-0002-crit', 'The form serializer is never fed user input', 'history:')
        ->and($ledger)->not->toContain('prototype pollution')
        ->and(file_get_contents(base_path('.larapilot/cache/.gitignore')))->toBe("*\n");
});

it('refuses a decision it cannot record', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    sbomProject();
    fakeOsv();
    vendorAudit();

    expect(Artisan::call('larapilot:vendor-link', ['ids' => 'GHSA-none-0000-0000', '--waive' => true, '--reason' => 'x']))->toBe(2)
        ->and(Artisan::output())->toContain('Not in the last check');
    expect(Artisan::call('larapilot:vendor-link', ['ids' => 'GHSA-axio-0001-high', '--waive' => true]))->toBe(2)
        ->and(Artisan::output())->toContain('needs a reason');
    expect(Artisan::call('larapilot:vendor-link', ['ids' => 'GHSA-axio-0001-high', '--waive' => true, '--spec' => 'US-001']))->toBe(2);
    expect(Artisan::call('larapilot:vendor-audit', ['--fail-on' => 'severe']))->toBe(2);
});

it('answers the last check, and says when there is none or OSV.dev is down', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    sbomProject();

    expect(Artisan::call('larapilot:vendor-audit', ['--cached' => true]))->toBe(4)
        ->and(Artisan::output())->toContain('No vulnerability check was made yet');

    Http::fake(['api.osv.dev/*' => Http::response('', 503)]);

    expect(Artisan::call('larapilot:vendor-audit'))->toBe(3)
        ->and(Artisan::output())->toContain('OSV.dev answered 503');
});

it('summarises the SBOM and writes it as Markdown and CycloneDX', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    sbomProject();
    fakeOsv();
    vendorAudit();

    expect(Artisan::call('larapilot:sbom', ['--write' => 'both']))->toBe(0);
    $data = json_decode(Artisan::output(), true)['data'];

    expect($data)->not->toHaveKey('components')
        ->and($data['totals']['components'])->toBe(9)
        ->and($data['abandoned'])->toBe([['name' => 'old/thing', 'version' => '1.0.0', 'replacement' => 'new/thing']])
        ->and($data['vulnerabilities']['counts']['critical'])->toBe(1)
        ->and($data['written'])->toBe(['.larapilot/docs/security/sbom.md', '.larapilot/docs/security/sbom.cdx.json']);

    $markdown = (string) file_get_contents(base_path('.larapilot/docs/security/sbom.md'));
    $bom = json_decode((string) file_get_contents(base_path('.larapilot/docs/security/sbom.cdx.json')), true);
    $refs = array_column($bom['components'], 'bom-ref');

    expect($markdown)->toContain('# SBOM', '## Laravel (Composer) — 4 components', 'guzzlehttp/psr7 | 2.4.0', 'GHSA-wxmh-65f7-jcvw', 'old/thing _(abandoned)_')
        ->and($bom)->toMatchArray(['bomFormat' => 'CycloneDX', 'specVersion' => '1.5'])
        ->and($bom['components'])->toHaveCount(9)
        ->and($refs)->toContain('pkg:composer/guzzlehttp/psr7@2.4.0', 'pkg:npm/axios@1.6.0')
        ->and(collect($bom['components'])->firstWhere('bom-ref', 'pkg:composer/guzzlehttp/psr7@2.4.0'))->toMatchArray(['group' => 'guzzlehttp', 'name' => 'psr7', 'licenses' => [['license' => ['id' => 'MIT']]]])
        ->and(collect($bom['vulnerabilities'])->pluck('id')->all())->toContain('GHSA-axio-0002-crit')
        ->and(collect($bom['vulnerabilities'])->firstWhere('id', 'GHSA-axio-0001-high')['affects'])->toBe([['ref' => 'pkg:npm/axios@1.6.0']]);

    expect(Artisan::call('larapilot:sbom', ['--full' => true]))->toBe(0)
        ->and(json_decode(Artisan::output(), true)['data']['components'])->toHaveCount(9);

    expect(Artisan::call('larapilot:sbom', ['--write' => 'pdf']))->toBe(2);
});

it('writes one component for a package two inventories share, and a fix Yarn 1 can run', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    sbomProject();
    fakeOsv();

    $frontend = sys_get_temp_dir().'/larapilot-sbom-fe-'.bin2hex(random_bytes(6));
    $this->sbomFolders[] = $frontend;
    mkdir($frontend, 0755, true);
    file_put_contents($frontend.'/package.json', json_encode(['dependencies' => ['axios' => '^1.6.0']]));
    file_put_contents($frontend.'/yarn.lock', <<<'LOCK'
# THIS IS AN AUTOGENERATED FILE. DO NOT EDIT THIS FILE DIRECTLY.
# yarn lockfile v1


axios@^1.6.0:
  version "1.6.1"

follow-redirects@^1.15.0:
  version "1.15.2"

LOCK);
    config(['larapilot.frontend.repo_path' => $frontend]);

    [, $audit] = vendorAudit();
    $fixes = collect($audit['data']['packages'])->where('package', 'axios')->pluck('fix', 'version')->all();

    // The same package and version in the assets and in the companion is one component.
    $bom = app(SbomService::class)->cyclonedx();
    $refs = array_column($bom['components'], 'bom-ref');
    $shared = collect($bom['components'])->firstWhere('bom-ref', 'pkg:npm/follow-redirects@1.15.2');

    expect($fixes)->toBe(['1.6.0' => 'npm update axios', '1.6.1' => 'yarn upgrade axios'])
        ->and($refs)->toHaveCount(count(array_unique($refs)))
        ->and(collect($shared['properties'])->where('name', 'larapilot:inventory')->pluck('value')->all())->toBe(['assets', 'companion']);
});

it('takes the strictest license where several hold together', function (): void {
    expect(SbomService::licenseClass(['MIT AND GPL-3.0-only']))->toBe('strong-copyleft')
        ->and(SbomService::licenseClass(['(MIT AND LGPL-2.1-only) OR Apache-2.0']))->toBe('permissive')
        ->and(SbomService::licenseClass(['MIT OR GPL-3.0-only']))->toBe('permissive')
        ->and(SbomService::licenseClass(['MIT', 'GPL-3.0-only']))->toBe('permissive')
        ->and(SbomService::licenseClass(['LGPL-3.0-only AND SEE LICENSE IN LICENSE']))->toBe('weak-copyleft')
        ->and(SbomService::licenseClass([]))->toBe('unknown');
});

it('serves the SBOM page, its downloads, and the check', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    sbomProject();

    $this->get('/larapilot/sbom')
        ->assertOk()
        ->assertSee('SBOM')
        ->assertSee('Not checked for vulnerabilities yet')
        ->assertSee('guzzlehttp/psr7')
        ->assertSee('Laravel frontend assets');

    $this->get('/larapilot/sbom/vendor-audit.md')->assertNotFound();

    fakeOsv();

    $this->post('/larapilot/sbom/audit')
        ->assertRedirect('/larapilot/sbom')
        ->assertSessionHas('larapilot_success');

    $this->get('/larapilot/sbom')
        ->assertOk()
        ->assertSee('Vulnerabilities stop a release')
        ->assertSee('GHSA-axio-0002-crit')
        ->assertSee('npm update axios');

    $markdown = $this->get('/larapilot/sbom/sbom.md');
    $markdown->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($markdown->headers->get('Content-Disposition'))->toContain('-sbom-')
        ->and($markdown->getContent())->toContain('# SBOM');

    $bom = $this->get('/larapilot/sbom/sbom.cdx.json');
    $bom->assertOk();

    expect(json_decode((string) $bom->getContent(), true)['bomFormat'])->toBe('CycloneDX');

    $this->get('/larapilot/sbom/vendor-audit.md')->assertOk()->assertSee('# Vulnerable dependencies', false);

    // A fresh client: the stubs of fakeOsv() would answer first.
    Http::swap(new Factory);
    Http::fake(['api.osv.dev/*' => Http::response('', 500)]);

    $this->post('/larapilot/sbom/audit')
        ->assertRedirect('/larapilot/sbom')
        ->assertSessionHas('larapilot_error');
});
