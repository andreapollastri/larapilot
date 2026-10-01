<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\ConfigService;
use Larapilot\Services\ContextService;
use Larapilot\Services\Frontend\AgentRules;
use Larapilot\Services\Frontend\RepoFiles;
use Larapilot\Support\EnvWriter;
use Symfony\Component\Yaml\Yaml;

/**
 * A frontend repository on disk: path => content, arrays written as JSON.
 *
 * @param  array<string, string|array<mixed>>  $files
 */
function frontendFixture(array $files, array $symlinks = []): string
{
    $root = sys_get_temp_dir().'/larapilot-fe-ws-'.bin2hex(random_bytes(6));
    mkdir($root, 0755, true);
    $GLOBALS['frontendFixtures'][] = $root;
    frontendWrite($root, $files);

    foreach ($symlinks as $link => $target) {
        symlink($target, $root.'/'.$link);
    }

    return realpath($root) ?: $root;
}

/**
 * @param  array<string, string|array<mixed>>  $files
 */
function frontendWrite(string $root, array $files): void
{
    foreach ($files as $path => $content) {
        $absolute = $root.'/'.$path;

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0755, true);
        }

        file_put_contents($absolute, is_array($content)
            ? json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $content);
    }
}

afterEach(function (): void {
    foreach ($GLOBALS['frontendFixtures'] ?? [] as $root) {
        if (is_dir($root)) {
            shell_exec('rm -rf '.escapeshellarg($root));
        }
    }

    $GLOBALS['frontendFixtures'] = [];
});

function frontendGit(string $root, string $message): string
{
    $git = 'git -C '.escapeshellarg($root).' -c user.email=test@example.com -c user.name=Test ';

    if (! is_dir($root.'/.git')) {
        shell_exec('git init -q -b main --template= '.escapeshellarg($root));
    }

    shell_exec($git.'add -A');
    shell_exec($git.'commit -q --no-gpg-sign --allow-empty -m '.escapeshellarg($message));

    return trim((string) shell_exec($git.'rev-parse HEAD'));
}

/**
 * @return array<string, mixed>
 */
function frontendCall(string $command, array $parameters = []): array
{
    Artisan::call($command, $parameters);

    return json_decode(Artisan::output(), true) ?? [];
}

/**
 * An Nx workspace with two Angular apps, a library owned by one of them,
 * and one both share — plus the rules a team writes for its agents.
 *
 * @return array<string, string|array<mixed>>
 */
function nxAngularWorkspace(): array
{
    $component = <<<'TS'
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { OrdersService } from './orders.service';
import { ButtonComponent } from '@acme/shared/ui';

@Component({
  selector: 'acme-order-list',
  imports: [ButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './order-list.component.html',
})
export class OrderListComponent {
  private readonly orders = inject(OrdersService);
  readonly customer = input.required<string>();
  readonly open = signal(false);
}
TS;

    return [
        'nx.json' => [
            'defaultBase' => 'main',
            'plugins' => [
                ['plugin' => '@nx/eslint/plugin', 'options' => ['targetName' => 'lint']],
                '@nx/jest/plugin',
            ],
            'generators' => ['@nx/angular:component' => ['style' => 'scss', 'changeDetection' => 'OnPush']],
        ],
        'package.json' => [
            'name' => '@acme/source',
            'private' => true,
            'packageManager' => 'pnpm@9.12.0',
            'scripts' => ['api:generate' => 'orval --config orval.config.ts'],
            'dependencies' => ['@angular/core' => '~19.2.0', '@ngrx/signals' => '^19.0.0', '@angular/material' => '^19.2.0'],
            'devDependencies' => ['nx' => '20.4.0', '@nx/angular' => '20.4.0', 'prettier' => '^3.4.0', 'orval' => '^7.0.0', 'jest' => '^29.7.0'],
        ],
        'pnpm-lock.yaml' => "lockfileVersion: '9.0'\n",
        'eslint.config.js' => "module.exports = [{ rules: { '@nx/enforce-module-boundaries': 'error' } }];\n",
        'tsconfig.base.json' => <<<'JSONC'
{
  // Path aliases for the libraries
  "compilerOptions": {
    "paths": {
      "@acme/shared/ui": ["libs/shared/ui/src/index.ts"],
      "@acme/portal/feature-orders": ["libs/portal/feature-orders/src/index.ts"],
    },
  },
}
JSONC,
        'orval.config.ts' => "export default { acme: { input: { target: 'http://localhost:8000/docs/api.json' }, output: { target: 'libs/shared/api/src/generated.ts' } } };\n",
        'AGENTS.md' => "# Acme frontend\n\nUse signals and standalone components. Follow @docs/conventions.md before writing.\nSee [the style guide](docs/style-guide.md).\n",
        'docs/conventions.md' => "Selectors start with acme-.\n",
        'docs/style-guide.md' => "Spacing tokens only.\n",
        'CONTRIBUTING.md' => "Conventional commits.\n",
        '.cursor/rules/angular.mdc' => "---\ndescription: Angular components\nglobs: *.component.ts\nalwaysApply: false\n---\nOnPush everywhere.\n",
        '.cursor/rules/testing.mdc' => "---\ndescription: How we test stores\nalwaysApply: false\n---\nUse spectator.\n",
        '.github/copilot-instructions.md' => "Never use any.\n",
        '.github/instructions/specs.instructions.md' => "---\napplyTo: \"**/*.spec.ts\"\n---\nOne describe per file.\n",
        'tools/workspace-plugin/package.json' => ['name' => '@acme/workspace-plugin'],
        'tools/workspace-plugin/generators.json' => ['generators' => ['feature-lib' => ['factory' => './src/feature-lib', 'description' => 'Feature library with our layout']]],
        'apps/portal/project.json' => [
            'name' => 'portal',
            'projectType' => 'application',
            'prefix' => 'acme',
            'sourceRoot' => 'apps/portal/src',
            'tags' => ['scope:portal', 'type:app'],
            'targets' => [
                'build' => ['executor' => '@angular-devkit/build-angular:application'],
                'serve' => ['executor' => '@angular-devkit/build-angular:dev-server'],
                'test' => ['executor' => '@nx/jest:jest'],
            ],
        ],
        'apps/portal/AGENTS.md' => "Portal pages live under src/app/pages.\n",
        'apps/portal/src/main.ts' => "bootstrapApplication(AppComponent, appConfig);\n",
        'apps/portal/src/app/app.config.ts' => "export const appConfig = { providers: [provideZonelessChangeDetection()] };\n",
        'apps/portal/src/app/app.routes.ts' => "import { ordersRoutes } from '@acme/portal/feature-orders';\nexport const routes = [{ path: 'orders', children: ordersRoutes }];\n",
        'apps/portal/src/app/app.component.ts' => "import { Component } from '@angular/core';\n@Component({ selector: 'acme-root', template: `@if (ready) { <router-outlet /> }` })\nexport class AppComponent { ready = true; }\n",
        'apps/admin/project.json' => [
            'name' => 'admin',
            'projectType' => 'application',
            'sourceRoot' => 'apps/admin/src',
            'tags' => ['scope:admin'],
            'targets' => ['build' => ['executor' => '@angular-devkit/build-angular:application']],
        ],
        'apps/admin/src/app/app.component.ts' => "import { ButtonComponent } from '@acme/shared/ui';\n",
        'apps/portal-e2e/project.json' => ['name' => 'portal-e2e', 'targets' => ['e2e' => ['executor' => '@nx/playwright:playwright']]],
        'libs/shared/ui/project.json' => ['name' => 'shared-ui', 'projectType' => 'library', 'sourceRoot' => 'libs/shared/ui/src', 'tags' => ['scope:shared']],
        'libs/shared/ui/src/index.ts' => "export * from './lib/button.component';\n",
        'libs/shared/ui/src/lib/button.component.ts' => "@Component({ selector: 'acme-button', template: '' })\nexport class ButtonComponent {}\n",
        'libs/portal/feature-orders/project.json' => ['name' => 'portal-feature-orders', 'projectType' => 'library', 'sourceRoot' => 'libs/portal/feature-orders/src', 'tags' => ['scope:portal']],
        'libs/portal/feature-orders/jest.config.ts' => "export default {};\n",
        'libs/portal/feature-orders/src/index.ts' => "export * from './lib/orders.routes';\n",
        'libs/portal/feature-orders/src/lib/orders.routes.ts' => "export const ordersRoutes = [];\n",
        'libs/portal/feature-orders/src/lib/order-list.component.ts' => $component,
        'libs/portal/feature-orders/src/lib/order-list.component.html' => "@for (order of orders; track order.id) { <acme-button /> }\n",
        'libs/portal/feature-orders/src/lib/order-list.component.spec.ts' => "describe('OrderListComponent', () => {});\n",
        'libs/portal/feature-orders/src/lib/orders.service.ts' => "@Injectable({ providedIn: 'root' })\nexport class OrdersService { private http = inject(HttpClient); }\n",
        'libs/portal/feature-orders/src/lib/payments/AGENTS.md' => "Payments call the PSP SDK only through PaymentsFacade.\n",
    ];
}

it('reads an Nx Angular monorepo from its files and asks which projects belong to the product', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    $root = frontendFixture(nxAngularWorkspace(), ['CLAUDE.md' => 'AGENTS.md']);

    $scan = frontendCall('larapilot:frontend-scan', ['--path' => $root, '--no-cli' => true]);

    expect($scan['kind'])->toBe('frontend-scan')
        ->and($scan['data']['workspace']['kind'])->toBe('nx')
        ->and($scan['data']['workspace']['tool'])->toBe(['name' => 'nx', 'version' => '20.4.0'])
        ->and($scan['data']['workspace']['package_manager']['name'])->toBe('pnpm')
        ->and($scan['data']['workspace']['package_manager']['exec'])->toBe('pnpm exec')
        ->and($scan['data']['workspace']['graph']['source'])->toBe('files')
        ->and($scan['data']['targets']['needs_project'])->toBeTrue()
        ->and($scan['data']['targets']['candidates'])->toContain('portal', 'admin')
        ->and($scan['data']['target_projects'])->toBe([])
        ->and(implode("\n", $scan['data']['warnings']))->toContain('no target is set');

    $byName = collect($scan['data']['projects'])->keyBy('name');

    expect($byName['portal']['type'])->toBe('application')
        ->and($byName['portal']['stack'])->toBe('Angular')
        ->and($byName['shared-ui']['type'])->toBe('library')
        ->and($byName['portal-e2e']['type'])->toBe('e2e');
});

it('profiles the target project of an Nx workspace: scope, rules, conventions, commands', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    $root = frontendFixture(nxAngularWorkspace(), ['CLAUDE.md' => 'AGENTS.md']);

    expect(Artisan::call('larapilot:frontend-set', ['--path' => $root, '--project' => ['portal']]))->toBe(0);

    $scan = frontendCall('larapilot:frontend-scan', ['--no-cli' => true])['data'];
    $portal = $scan['target_projects'][0];

    expect($scan['targets']['resolved'])->toBe(['portal'])
        ->and($portal['stack'])->toBe('Angular')
        ->and($portal['framework'])->toBe(['package' => '@angular/core', 'version' => '19.2.0', 'major' => 19])
        ->and($portal['prefix'])->toBe('acme')
        ->and($portal['depends_on'])->toBe(['portal-feature-orders'])
        ->and($portal['commands']['test'])->toBe('CI=true pnpm exec nx run portal:test')
        ->and($portal['commands']['lint'])->toBe('pnpm exec nx run portal:lint')
        ->and($portal['commands']['build'])->toBe('pnpm exec nx run portal:build')
        ->and($scan['commands']['affected'])->toBe('CI=true pnpm exec nx affected -t lint test build --base=main')
        ->and($scan['commands']['format_check'])->toBe('pnpm exec nx format:check --base=main')
        ->and($scan['playbooks'])->toBe(['.larapilot/runtime-frontend-angular.md']);

    // The library only the portal uses is the portal's; the one admin also uses is shared.
    expect(array_column($scan['write_scope']['owned'], 'name'))->toBe(['portal', 'portal-feature-orders'])
        ->and($scan['write_scope']['shared'])->toBe([
            ['name' => 'shared-ui', 'root' => 'libs/shared/ui', 'used_by' => 1, 'apps' => ['admin']],
        ]);

    $mustRead = array_column($scan['rules']['must_read'], 'path');

    expect($mustRead)->toBe(['AGENTS.md', 'docs/conventions.md', '.github/copilot-instructions.md', 'apps/portal/AGENTS.md'])
        ->and($scan['rules']['must_read'][0]['same_as'])->toBe(['CLAUDE.md'])
        ->and($scan['rules']['must_read'][1]['via'])->toBe('AGENTS.md')
        ->and(array_column($scan['rules']['conditional'], 'path'))->toContain(
            '.cursor/rules/angular.mdc',
            '.github/instructions/specs.instructions.md',
            'libs/portal/feature-orders/src/lib/payments/AGENTS.md'
        )
        ->and(array_column($scan['rules']['on_request'], 'path'))->toBe(['.cursor/rules/testing.mdc'])
        ->and(array_column($scan['rules']['references'], 'path'))->toContain('CONTRIBUTING.md', 'docs/style-guide.md');

    $observed = implode("\n", $portal['observed']);

    expect($observed)->toContain('Language: TypeScript')
        ->and($observed)->toContain('the app is zoneless')
        ->and($observed)->toContain('built-in blocks')
        ->and($observed)->toContain('Selector prefix: acme (project config)')
        ->and($observed)->toContain('@nx/angular:component style=scss');

    expect($portal['libraries']['state'])->toBe(['@ngrx/signals'])
        ->and($portal['libraries']['ui'])->toContain('@angular/material')
        ->and(array_column($portal['exemplars'], 'kind'))->toContain('component')
        ->and($scan['generators']['local'][0]['name'])->toBe('@acme/workspace-plugin:feature-lib')
        ->and($scan['generators']['collections'])->toContain('@nx/angular')
        ->and($scan['api_client']['generated'])->toBeTrue()
        ->and($scan['api_client']['generators'][0]['name'])->toBe('orval')
        ->and($scan['api_client']['generators'][0]['inputs'])->toBe(['http://localhost:8000/docs/api.json'])
        ->and($scan['api_client']['regenerate'])->toBe(['pnpm run api:generate']);
});

it('lists the rules that govern the files about to be written', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    $root = frontendFixture(nxAngularWorkspace(), ['CLAUDE.md' => 'AGENTS.md']);
    app(ConfigService::class)->updateFrontend(['repo_path' => $root, 'projects' => ['portal']]);

    $component = frontendCall('larapilot:frontend-rules', [
        '--file' => ['libs/portal/feature-orders/src/lib/order-list.component.ts'],
    ])['data'];

    expect(array_column($component['must_read'], 'path'))->toBe([
        'AGENTS.md', 'docs/conventions.md', '.cursor/rules/angular.mdc', '.github/copilot-instructions.md',
    ])
        ->and($component['must_read'][2]['matched'])->toBe(['libs/portal/feature-orders/src/lib/order-list.component.ts'])
        ->and($component['fingerprint'])->toBeString();

    $payments = frontendCall('larapilot:frontend-rules', [
        '--file' => [$root.'/libs/portal/feature-orders/src/lib/payments/checkout.spec.ts'],
    ])['data'];

    expect(array_column($payments['must_read'], 'path'))->toContain(
        '.github/instructions/specs.instructions.md',
        'libs/portal/feature-orders/src/lib/payments/AGENTS.md'
    )->and(array_column($payments['must_read'], 'path'))->not->toContain('.cursor/rules/angular.mdc');

    expect(Artisan::call('larapilot:frontend-rules', ['--file' => ['../elsewhere/main.ts']]))->toBe(2);
});

it('refuses a project the workspace does not have and names the ones it has', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    $root = frontendFixture(nxAngularWorkspace());

    $failure = frontendCall('larapilot:frontend-set', ['--path' => $root, '--project' => ['shop']]);

    expect($failure['kind'] ?? null)->toBe('error')
        ->and($failure['error']['hint'] ?? '')->toContain('admin, portal');

    // Nothing was saved: the path was refused with the project.
    expect(app(ConfigService::class)->frontend()['configured'])->toBeFalse()
        ->and(Artisan::call('larapilot:frontend-set', ['--project' => ['portal']]))->toBe(4);

    expect(Artisan::call('larapilot:frontend-set', ['--path' => $root, '--project' => ['shop'], '--skip-check' => true]))->toBe(0)
        ->and(app(ConfigService::class)->frontend()['projects'])->toBe(['shop']);

    expect(Artisan::call('larapilot:frontend-set', ['--mode' => 'later']))->toBe(2);
});

it('keeps the frontend path out of config.yaml whatever else frontend-set changes', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    $root = frontendFixture(nxAngularWorkspace());
    $config = app(ConfigService::class);

    expect(Artisan::call('larapilot:frontend-set', ['--path' => $root]))->toBe(0)
        ->and(Artisan::call('larapilot:frontend-set', ['--project' => 'portal,admin', '--mode' => 'handoff', '--stack' => 'Angular']))->toBe(0);

    $yaml = (string) file_get_contents($config->configPath());
    $frontend = Yaml::parse($yaml)['frontend'];

    expect($yaml)->not->toContain($root)
        ->and($frontend)->toBe(['stack' => 'Angular', 'projects' => ['portal', 'admin'], 'mode' => 'handoff'])
        ->and($config->frontend()['repo_path'])->toBe($root);

    // A path an older version wrote into the YAML moves to .env.
    EnvWriter::set('LARAPILOT_FRONTEND_REPO_PATH', '');
    $parsed = Yaml::parse($yaml);
    $parsed['frontend']['repo_path'] = $root;
    file_put_contents($config->configPath(), Yaml::dump($parsed, 4, 2));

    $config->updateFrontend(['projects' => []]);

    expect((string) file_get_contents($config->configPath()))->not->toContain($root)
        ->and(EnvWriter::get('LARAPILOT_FRONTEND_REPO_PATH'))->toBe($root)
        ->and(Artisan::call('larapilot:frontend-set', ['--clear-projects' => true]))->toBe(0)
        ->and($config->frontend()['projects'])->toBe([]);
});

it('reads the graph with the nx the workspace installed and keeps it until the workspace moves', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    $graph = json_encode(['graph' => [
        'nodes' => [
            'web' => ['name' => 'web', 'type' => 'app', 'data' => ['root' => 'apps/web', 'sourceRoot' => 'apps/web/src', 'projectType' => 'application', 'tags' => ['scope:web'], 'targets' => [
                'build' => ['executor' => '@nx/vite:build'],
                'test' => ['executor' => '@nx/vite:test'],
                'lint' => ['executor' => '@nx/eslint:lint'],
            ]]],
            'ui' => ['name' => 'ui', 'type' => 'lib', 'data' => ['root' => 'libs/ui', 'tags' => [], 'targets' => ['test' => ['executor' => '@nx/vite:test']]]],
        ],
        'dependencies' => [
            'web' => [['source' => 'web', 'target' => 'ui', 'type' => 'static'], ['source' => 'web', 'target' => 'npm:react', 'type' => 'static']],
            'ui' => [],
        ],
    ]]);

    $root = frontendFixture([
        'nx.json' => ['plugins' => ['@acme/custom-plugin']],
        'package.json' => ['name' => 'web-source', 'dependencies' => ['react' => '^19.0.0'], 'devDependencies' => ['nx' => '21.0.0']],
        'package-lock.json' => '{}',
        'apps/web/src/main.tsx' => "export function App() { return null; }\n",
        'node_modules/.bin/nx' => "#!/bin/sh\nfor arg in \"\$@\"; do case \"\$arg\" in --file=*) out=\"\${arg#--file=}\";; esac; done\ncat > \"\$out\" <<'JSON'\n{$graph}\nJSON\necho called >> \"\$(dirname \"\$0\")/../../calls.log\"\n",
    ]);
    chmod($root.'/node_modules/.bin/nx', 0755);

    $first = frontendCall('larapilot:frontend-scan', ['--path' => $root, '--project' => ['web']])['data'];

    expect($first['workspace']['graph'])->toBe(['source' => 'nx-cli', 'cached' => false, 'error' => null, 'targets_complete' => true])
        ->and($first['target_projects'][0]['depends_on'])->toBe(['ui'])
        ->and($first['target_projects'][0]['stack'])->toBe('React')
        ->and($first['target_projects'][0]['commands']['test'])->toBe('CI=true npx nx run web:test')
        ->and(array_column($first['write_scope']['owned'], 'name'))->toBe(['web', 'ui'])
        ->and($first['playbooks'])->toBe(['.larapilot/runtime-frontend-react.md']);

    $second = frontendCall('larapilot:frontend-scan', ['--path' => $root, '--project' => ['web']])['data'];

    expect($second['workspace']['graph']['cached'])->toBeTrue()
        ->and(substr_count((string) file_get_contents($root.'/calls.log'), 'called'))->toBe(1);

    frontendCall('larapilot:frontend-scan', ['--path' => $root, '--project' => ['web'], '--fresh' => true]);

    expect(substr_count((string) file_get_contents($root.'/calls.log'), 'called'))->toBe(2);
});

it('reads pnpm workspaces with a catalog and a Vue app', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    $root = frontendFixture([
        'package.json' => ['name' => 'acme', 'private' => true],
        'pnpm-lock.yaml' => "lockfileVersion: '9.0'\n",
        'pnpm-workspace.yaml' => "packages:\n  - 'apps/*'\n  - 'packages/*'\ncatalog:\n  vue: ^3.5.13\n",
        'apps/shop/package.json' => [
            'name' => '@acme/shop',
            'scripts' => ['dev' => 'vite', 'build' => 'vite build', 'test' => 'vitest', 'lint' => 'eslint .'],
            'dependencies' => ['vue' => 'catalog:', 'pinia' => '^2.3.0', '@acme/ui' => 'workspace:*'],
        ],
        'apps/shop/vite.config.ts' => "import vue from '@vitejs/plugin-vue';\nexport default { plugins: [vue()] };\n",
        'apps/shop/src/App.vue' => "<script setup lang=\"ts\">\nconst x = 1;\n</script>\n<template><div /></template>\n<style scoped></style>\n",
        'apps/shop/src/stores/cart.ts' => "export const useCart = defineStore('cart', () => { return {}; });\n",
        'apps/blog/package.json' => ['name' => '@acme/blog', 'scripts' => ['dev' => 'vite'], 'dependencies' => ['vue' => 'catalog:', '@acme/ui' => 'workspace:*']],
        'packages/ui/package.json' => ['name' => '@acme/ui', 'main' => 'src/index.ts', 'scripts' => ['build' => 'vite build', 'test' => 'vitest']],
        'packages/ui/src/index.ts' => "export {};\n",
    ]);

    $scan = frontendCall('larapilot:frontend-scan', ['--path' => $root, '--project' => ['apps/shop']])['data'];
    $shop = $scan['target_projects'][0];

    expect($scan['workspace']['kind'])->toBe('pnpm-workspaces')
        ->and($shop['name'])->toBe('@acme/shop')
        ->and($shop['stack'])->toBe('Vue')
        ->and($shop['framework']['version'])->toBe('3.5.13')
        ->and($shop['commands']['test'])->toBe('CI=true pnpm --filter @acme/shop run test')
        ->and($shop['commands']['serve'])->toBe('pnpm --filter @acme/shop run dev')
        ->and($scan['commands']['affected'])->toContain('pnpm --filter "...[main]" run lint')
        ->and($scan['write_scope']['shared'][0]['name'])->toBe('@acme/ui')
        ->and($scan['write_scope']['shared'][0]['apps'])->toBe(['@acme/blog'])
        ->and(implode("\n", $shop['observed']))->toContain('1 with <script setup>')
        ->and(implode("\n", $shop['observed']))->toContain('Pinia stores: 1 setup stores')
        ->and($scan['playbooks'])->toBe(['.larapilot/runtime-frontend-vue.md'])
        ->and(implode("\n", $scan['warnings']))->toContain('pnpm install --frozen-lockfile');
});

it('reads an Angular CLI workspace with NgModules and Karma', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    $root = frontendFixture([
        'angular.json' => ['projects' => ['my-app' => [
            'root' => '',
            'sourceRoot' => 'src',
            'projectType' => 'application',
            'prefix' => 'app',
            'architect' => [
                'build' => ['builder' => '@angular-devkit/build-angular:application'],
                'test' => ['builder' => '@angular-devkit/build-angular:karma'],
            ],
        ]]],
        'package.json' => ['name' => 'my-app', 'dependencies' => ['@angular/core' => '^17.3.0'], 'devDependencies' => ['@angular/cli' => '^17.3.0', 'karma' => '~6.4.0']],
        'package-lock.json' => '{}',
        'src/main.ts' => "platformBrowserDynamic().bootstrapModule(AppModule);\n",
        'src/app/app.module.ts' => "@NgModule({ declarations: [AppComponent] })\nexport class AppModule {}\n",
        'src/app/app.component.ts' => "@Component({ selector: 'app-root', templateUrl: './app.component.html' })\nexport class AppComponent { constructor(private http: HttpClient) {} @Input() name = ''; }\n",
        'src/app/app.component.html' => "<div *ngIf=\"name\">{{ name }}</div>\n",
    ]);

    $scan = frontendCall('larapilot:frontend-scan', ['--path' => $root])['data'];
    $app = $scan['target_projects'][0];
    $observed = implode("\n", $app['observed']);

    expect($scan['workspace']['kind'])->toBe('angular-cli')
        ->and($scan['targets']['resolved'])->toBe(['my-app'])
        ->and($app['commands']['test'])->toBe('CI=true npx ng test my-app --watch=false')
        ->and($app['commands']['build'])->toBe('npx ng build my-app')
        ->and($observed)->toContain('0 standalone, 1 declared in NgModules (1 NgModule files)')
        ->and($observed)->toContain('1 structural directives')
        ->and($observed)->toContain('1 decorators')
        ->and($observed)->toContain('1 constructor parameters')
        ->and($scan['api_client']['handwritten'])->toBe(['HttpClient' => 1])
        ->and($scan['generators']['run'])->toBe('npx ng generate <schematic> <name> --project=my-app --dry-run');
});

it('parses rule frontmatter the way editors write it', function (): void {
    [$meta] = AgentRules::frontmatter("---\nglobs: *.tsx, src/**/*.{ts,tsx}\nalwaysApply: false\n---\nbody");

    expect($meta['globs'])->toBe('*.tsx, src/**/*.{ts,tsx}')
        ->and(AgentRules::splitList($meta['globs']))->toBe(['*.tsx', 'src/**/*.{ts,tsx}'])
        ->and($meta['alwaysApply'])->toBeFalse()
        ->and(RepoFiles::matches('*.tsx', 'apps/web/src/Button.tsx'))->toBeTrue()
        ->and(RepoFiles::matches('src/**/*.{ts,tsx}', 'src/a/b.ts'))->toBeTrue()
        ->and(RepoFiles::matches('src/**/*.{ts,tsx}', 'lib/a.ts'))->toBeFalse()
        ->and(RepoFiles::stripJsonComments("{\"a\": 1, // note\n \"b\": \"//x\",}"))->toBe("{\"a\": 1, \n \"b\": \"//x\"}");

    $root = frontendFixture([
        '.windsurf/rules/api.md' => "---\ntrigger: model_decision\ndescription: API calls\n---\nUse the facade.\n",
        '.kiro/steering/tests.md' => "---\ninclusion: fileMatch\nfileMatchPattern: \"**/*.test.ts\"\n---\nUse vitest.\n",
        '.junie/guidelines.md' => "Be terse.\n",
        'package.json' => ['name' => 'single', 'dependencies' => ['react' => '^19.0.0']],
    ]);

    $rules = frontendCall('larapilot:frontend-rules', ['--path' => $root])['data'];

    expect(array_column($rules['must_read'], 'path'))->toBe(['.junie/guidelines.md'])
        ->and(array_column($rules['on_request'], 'path'))->toBe(['.windsurf/rules/api.md'])
        ->and($rules['conditional'][0]['path'])->toBe('.kiro/steering/tests.md')
        ->and($rules['conditional'][0]['when'])->toContain('**/*.test.ts');
});

it('finds the commit of a frontend task in the frontend repository', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $root = frontendFixture(['package.json' => ['name' => 'web', 'dependencies' => ['react' => '^19.0.0']]]);
    $sha = frontendGit($root, 'feat(US-001): TASK-02 order list');
    app(ConfigService::class)->updateFrontend(['repo_path' => $root]);

    addSpec();
    test()->artisan('larapilot:spec-plan', ['code' => 'US-001', '--file' => payloadFile([
        'plan_body' => 'Plan.',
        'tasks' => [
            ['id' => 'TASK-01', 'title' => 'API', 'type' => 'Impl', 'status' => 'TODO', 'body' => "## Description\nAPI."],
            ['id' => 'TASK-02', 'title' => 'Order list', 'type' => 'Impl', 'status' => 'TODO', 'repo' => 'frontend', 'body' => "## Description\nUI."],
        ],
    ], 'tmp-plan.yaml')])->assertSuccessful();

    $done = frontendCall('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-02']);

    expect($done['data']['commit']['sha'] ?? null)->toBe($sha)
        ->and($done['data']['commit']['subject'] ?? null)->toBe('feat(US-001): TASK-02 order list');
});

it('writes the handoff brief of a spec for the frontend team', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $root = frontendFixture(['package.json' => ['name' => 'web', 'scripts' => ['test' => 'vitest'], 'dependencies' => ['react' => '^19.0.0']]]);
    app(ConfigService::class)->updateFrontend(['repo_path' => $root, 'mode' => 'handoff']);

    file_put_contents(base_path('.larapilot/openapi-product.json'), json_encode(['openapi' => '3.1.0', 'paths' => [
        '/api/orders/{order}' => ['get' => ['operationId' => 'showOrder', 'summary' => 'Show an order']],
        '/api/users' => ['get' => ['summary' => 'List users']],
    ]]));

    addSpec();
    test()->artisan('larapilot:spec-plan', ['code' => 'US-001', '--file' => payloadFile([
        'plan_body' => 'Plan.',
        'tasks' => [
            ['id' => 'TASK-01', 'title' => 'Orders API', 'type' => 'Impl', 'status' => 'TODO', 'body' => "## Description\nAPI."],
            ['id' => 'TASK-02', 'title' => 'Order page', 'type' => 'Impl', 'status' => 'TODO', 'repo' => 'frontend', 'project' => 'web', 'dependencies' => ['TASK-01'], 'body' => "## Description\nShow the order from GET /api/orders/42."],
        ],
    ], 'tmp-plan.yaml')])->assertSuccessful();

    $brief = frontendCall('larapilot:frontend-brief', ['code' => 'US-001']);
    $content = (string) file_get_contents(base_path('.larapilot/docs/frontend-briefs/US-001.md'));

    expect($brief['data']['path'])->toBe('.larapilot/docs/frontend-briefs/US-001.md')
        ->and($brief['data']['tasks'])->toBe(['TASK-02'])
        ->and($content)->toContain('# Frontend brief — US-001 · Login')
        ->and($content)->toContain('### TASK-02 — Order page')
        ->and($content)->toContain('#### Description')
        ->and($content)->toContain('Project: `web`')
        ->and($content)->toContain('Waits for: TASK-01 (backend, TODO)')
        ->and($content)->toContain('`GET /api/orders/{order}` — Show an order')
        ->and($content)->not->toContain('/api/users')
        ->and($content)->toContain('- TASK-01 — Orders API (TODO)')
        ->and($content)->toContain('`CI=true npm run test`')
        ->and($content)->not->toContain($root);

    $stdout = frontendCall('larapilot:frontend-brief', ['code' => 'US-001', '--stdout' => true]);

    expect($stdout['data']['content'])->toContain('### TASK-02')
        ->and($stdout['data'])->not->toHaveKey('path');

    expect(Artisan::call('larapilot:frontend-brief', ['code' => 'US-001', '--task' => ['TASK-01']]))->toBe(4)
        ->and(Artisan::call('larapilot:frontend-brief', ['code' => 'US-404']))->toBe(4);
});

it('hands the companion protocol and the stack playbooks to the skills once a frontend is linked', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $context = static function (string $skill): array {
        Artisan::call('larapilot:context', ['skill' => $skill]);

        return json_decode(Artisan::output(), true)['data'];
    };

    $unlinked = $context('frontend-companion');

    expect(array_column($unlinked['runtime']['read'], 'file'))->not->toContain('frontend.md')
        ->and(array_column($unlinked['runtime']['on_demand'], 'file'))->toBe(['frontend.md'])
        ->and(array_column($context('plan')['runtime']['read'], 'file'))->not->toContain('frontend.md');

    $root = frontendFixture(['package.json' => ['name' => 'web', 'dependencies' => ['react' => '^19.0.0']]]);
    app(ConfigService::class)->updateFrontend(['repo_path' => $root, 'projects' => ['web'], 'mode' => 'handoff']);
    app()->forgetInstance(ContextService::class);

    foreach (['frontend-companion', 'plan', 'implement', 'review'] as $skill) {
        $data = $context($skill);

        expect(array_column($data['runtime']['read'], 'file'))->toContain('frontend.md')
            ->and(array_column($data['runtime']['on_demand'], 'file'))->toContain('frontend-angular.md', 'frontend-react.md', 'frontend-vue.md', 'frontend-svelte.md')
            ->and($data['frontend']['projects'])->toBe(['web'])
            ->and($data['frontend']['mode'])->toBe('handoff');
    }
});

it('sends every frontend task through the companion protocol instead of guessed commands', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $companion = (string) file_get_contents($root.'/boost/skills/larapilot-frontend-companion/SKILL.md');
    $implement = (string) file_get_contents($root.'/boost/skills/larapilot-implement/SKILL.md');
    $templates = (string) file_get_contents($root.'/larapilot/task-templates.md');
    $protocol = (string) file_get_contents($root.'/larapilot/runtime-frontend.md');

    expect($companion)->toContain('frontend-set --project=', 'frontend-rules --file=', 'frontend-brief', 'targets.needs_project')
        ->and($implement)->toContain('frontend-rules --file=', 'never a guessed `npm test`')
        ->and($templates)->toContain('"project": "<name>"', '## Frontend Rules', '`commands.affected`')
        ->and($templates)->not->toContain('`npm test` / `pnpm test`')
        ->and($protocol)->toContain('### 2. House rules', 'never `--no-verify`', '### 8. Handoff mode');

    foreach (['angular', 'react', 'vue', 'svelte'] as $stack) {
        expect((string) file_get_contents($root.'/larapilot/runtime-frontend-'.$stack.'.md'))
            ->toContain('### What each version adds', 'Order of authority: the repository\'s rules, then `observed`');
    }
});

/**
 * A monorepo whose apps are git repositories of their own, cloned inside it:
 * `apps/billing` builds with the monorepo's toolchain and libraries.
 *
 * @return array{workspace: string, app: string}
 */
function monorepoWithClonedApp(): array
{
    $workspace = frontendFixture([
        'nx.json' => ['defaultBase' => 'main'],
        'package.json' => [
            'name' => 'acme-frontend',
            'dependencies' => ['@angular/core' => '~18.2.0'],
            'devDependencies' => ['nx' => '20.1.0', '@angular-devkit/build-angular' => '~18.2.0', 'karma' => '~6.4.0', 'karma-chrome-launcher' => '~3.2.0'],
        ],
        'package-lock.json' => '{}',
        'tsconfig.base.json' => ['compilerOptions' => ['paths' => ['@acme/ui' => ['libs/ui/src/index.ts']]]],
        'AGENTS.md' => "Standalone components only.\n",
        'libs/ui/project.json' => ['name' => 'ui', 'projectType' => 'library', 'sourceRoot' => 'libs/ui/src', 'tags' => ['scope:shared']],
        'libs/ui/src/index.ts' => "export const ui = 1;\n",
        'apps/admin/project.json' => ['name' => 'admin', 'projectType' => 'application', 'sourceRoot' => 'apps/admin/src'],
        'apps/admin/src/main.ts' => "import { ui } from '@acme/ui';\n",
        '.gitignore' => "apps/billing\n",
    ]);
    frontendGit($workspace, 'chore: workspace');

    $app = $workspace.'/apps/billing';
    frontendWrite($app, [
        'project.json' => [
            'name' => 'billing',
            '$schema' => '../../node_modules/nx/schemas/project-schema.json',
            'projectType' => 'application',
            'sourceRoot' => 'apps/billing/src',
            'prefix' => 'bill',
            'targets' => [
                'build' => ['executor' => '@angular-devkit/build-angular:browser'],
                'test' => ['executor' => '@angular-devkit/build-angular:karma'],
            ],
        ],
        'tsconfig.json' => ['extends' => '../../tsconfig.base.json'],
        'src/app/invoice.component.ts' => "import { ui } from '@acme/ui';\n@Component({ selector: 'bill-invoice', template: '' })\nexport class InvoiceComponent {}\n",
        'src/app/invoice.component.spec.ts' => "describe('InvoiceComponent', () => {});\n",
    ]);
    frontendGit($app, 'feat: fattura in pdf');
    frontendGit($app, 'fix: totale arrotondato');

    return ['workspace' => $workspace, 'app' => $app];
}

it('builds a project kept in its own repository inside the monorepo it belongs to', function (): void {
    app(ConfigService::class)->writeProjectConfig();
    ['workspace' => $workspace, 'app' => $app] = monorepoWithClonedApp();

    expect(Artisan::call('larapilot:frontend-set', ['--path' => $app]))->toBe(0);

    $scan = frontendCall('larapilot:frontend-scan', ['--no-cli' => true])['data'];
    $billing = $scan['target_projects'][0];

    expect($scan['path'])->toBe($app)
        ->and($scan['root'])->toBe($workspace)
        ->and($scan['run_in'])->toBe($workspace)
        ->and($scan['workspace']['location']['source'])->toBe('ancestor')
        ->and($scan['workspace']['location']['project_root'])->toBe('apps/billing')
        ->and($scan['targets']['resolved'])->toBe(['billing'])
        ->and($billing['root'])->toBe('apps/billing')
        ->and($billing['git_root'])->toBe($app)
        ->and($billing['framework']['version'])->toBe('18.2.0')
        ->and($billing['prefix'])->toBe('bill')
        ->and($billing['commands']['test'])->toBe('CI=true npx nx run billing:test --watch=false --browsers=ChromeHeadless')
        ->and($billing['depends_on'])->toBe(['ui'])
        ->and($scan['write_scope']['shared'][0])->toMatchArray(['name' => 'ui', 'apps' => ['admin']])
        ->and(array_column($scan['rules']['must_read'], 'path'))->toBe(['AGENTS.md'])
        ->and($scan['commands'])->not->toHaveKey('affected')
        ->and($scan['git']['root'])->toBe($app)
        ->and($scan['git']['commits']['pattern'])->toBe('<type>: {code} TASK-NN <summary>')
        ->and($scan['git']['commits']['samples'])->toBe(['fix: totale arrotondato', 'feat: fattura in pdf'])
        ->and(implode("\n", $scan['warnings']))->toContain('billing is a git repository of its own inside the workspace');

    // A path written from the app repository is read inside the workspace.
    $rules = frontendCall('larapilot:frontend-rules', ['--file' => ['src/app/invoice.component.ts']])['data'];

    expect($rules['files'])->toBe(['apps/billing/src/app/invoice.component.ts'])
        ->and(array_column($rules['must_read'], 'path'))->toBe(['AGENTS.md']);
});

it('finds the commit of a frontend task in the repository of its project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    ['workspace' => $workspace, 'app' => $app] = monorepoWithClonedApp();
    $sha = frontendGit($app, 'feat: US-001 TASK-02 elenco fatture');
    app(ConfigService::class)->updateFrontend(['repo_path' => $workspace, 'projects' => ['billing']]);

    addSpec();
    test()->artisan('larapilot:spec-plan', ['code' => 'US-001', '--file' => payloadFile([
        'plan_body' => 'Plan.',
        'tasks' => [
            ['id' => 'TASK-02', 'title' => 'Invoice list', 'type' => 'Impl', 'status' => 'TODO', 'repo' => 'frontend', 'project' => 'billing', 'body' => "## Description\nUI."],
        ],
    ], 'tmp-plan.yaml')])->assertSuccessful();

    expect(frontendCall('larapilot:task-done', ['code' => 'US-001', 'taskId' => 'TASK-02'])['data']['commit']['sha'] ?? null)->toBe($sha);
});

it('says when the monorepo of a project is out of reach, and takes the one the user links', function (): void {
    app(ConfigService::class)->writeProjectConfig();

    $repository = frontendFixture([
        'project.json' => [
            'name' => 'reports',
            '$schema' => '../../node_modules/nx/schemas/project-schema.json',
            'projectType' => 'application',
            'sourceRoot' => 'apps/reports/src',
            'targets' => ['build' => ['executor' => '@angular-devkit/build-angular:browser']],
        ],
        'tsconfig.json' => ['extends' => '../../tsconfig.base.json'],
        'src/app/report.component.ts' => "@Component({ selector: 'app-report', template: '' })\nexport class ReportComponent {}\n",
    ]);

    $scan = frontendCall('larapilot:frontend-scan', ['--path' => $repository, '--no-cli' => true])['data'];

    expect($scan['workspace']['kind'])->toBe('nx-project')
        ->and($scan['run_in'])->toBeNull()
        ->and($scan['workspace']['location'])->toMatchArray(['source' => 'missing', 'expected_root' => 'apps/reports'])
        ->and($scan['target_projects'][0]['source_root'])->toBe('src')
        ->and($scan['target_projects'][0]['stack'])->toBe('Angular')
        ->and($scan['target_projects'][0]['commands']['build'])->toBe('npx nx run reports:build')
        ->and($scan['commands'])->toBe([])
        ->and(implode("\n", $scan['warnings']))->toContain('frontend-set --workspace=/absolute/path');

    $notWorkspace = frontendFixture(['README.md' => "Nothing here.\n"]);
    expect(Artisan::call('larapilot:frontend-set', ['--path' => $repository, '--workspace' => $notWorkspace]))->toBe(2);

    $workspace = frontendFixture([
        'nx.json' => [],
        'package.json' => ['name' => 'monorepo', 'dependencies' => ['@angular/core' => '~17.3.0']],
        'apps/reports/project.json' => ['name' => 'reports', 'projectType' => 'application', 'sourceRoot' => 'apps/reports/src', 'targets' => ['build' => ['executor' => '@angular-devkit/build-angular:browser']]],
    ]);

    expect(Artisan::call('larapilot:frontend-set', ['--path' => $repository, '--workspace' => $workspace]))->toBe(0);

    $linked = frontendCall('larapilot:frontend-scan', ['--no-cli' => true])['data'];

    expect($linked['run_in'])->toBe($workspace)
        ->and($linked['workspace']['location']['source'])->toBe('configured')
        ->and($linked['targets']['resolved'])->toBe(['reports'])
        ->and(app(ConfigService::class)->frontend()['workspace_path'])->toBe($workspace)
        ->and((string) file_get_contents(app(ConfigService::class)->configPath()))->not->toContain($workspace);
});

it('reads the agent rules of the monorepo around an app that is a workspace of its own', function (): void {
    app(ConfigService::class)->writeProjectConfig();

    $monorepo = frontendFixture([
        'nx.json' => [],
        'AGENTS.md' => "Commit subjects in Italian.\n",
        '.cursor/rules/components.mdc' => "---\nglobs: *.component.ts\n---\nOnPush.\n",
        'apps/other/AGENTS.md' => "Rules of another team.\n",
        'apps/shop/angular.json' => ['projects' => ['shop' => ['root' => '', 'sourceRoot' => 'src', 'projectType' => 'application', 'architect' => ['build' => ['builder' => '@angular-devkit/build-angular:application']]]]],
        'apps/shop/package.json' => ['name' => 'shop', 'dependencies' => ['@angular/core' => '^19.0.0']],
        'apps/shop/src/app/cart.component.ts' => "@Component({ selector: 'app-cart', template: '' })\nexport class CartComponent {}\n",
    ]);

    $scan = frontendCall('larapilot:frontend-scan', ['--path' => $monorepo.'/apps/shop'])['data'];

    expect($scan['workspace']['kind'])->toBe('angular-cli')
        ->and($scan['workspace']['enclosing'])->toBe($monorepo)
        ->and(array_column($scan['rules']['inherited']['must_read'], 'path'))->toBe([$monorepo.'/AGENTS.md'])
        ->and(array_column($scan['rules']['inherited']['conditional'], 'path'))->toBe([$monorepo.'/.cursor/rules/components.mdc'])
        ->and(json_encode($scan['rules']))->not->toContain('apps/other');

    $rules = frontendCall('larapilot:frontend-rules', ['--path' => $monorepo.'/apps/shop', '--file' => ['src/app/cart.component.ts']])['data'];

    expect(array_column($rules['inherited']['must_read'], 'path'))->toBe([$monorepo.'/AGENTS.md', $monorepo.'/.cursor/rules/components.mdc']);
});

it('keeps targets that cannot run out of the commands and runs Karma headless', function (): void {
    app(ConfigService::class)->writeProjectConfig();

    $root = frontendFixture([
        'angular.json' => ['projects' => ['loans' => [
            'root' => '',
            'sourceRoot' => 'src',
            'projectType' => 'application',
            'schematics' => ['@schematics/angular:component' => ['skipTests' => true]],
            'architect' => [
                'build' => ['builder' => '@angular-devkit/build-angular:browser'],
                'test' => ['builder' => '@angular-devkit/build-angular:karma', 'options' => ['karmaConfig' => 'karma.conf.js']],
                'lint' => ['builder' => '@angular-devkit/build-angular:tslint'],
                'e2e' => ['builder' => '@angular-devkit/build-angular:protractor'],
            ],
        ]]],
        'package.json' => ['name' => 'loans', 'dependencies' => ['@angular/core' => '^18.2.0'], 'devDependencies' => [
            '@angular-devkit/build-angular' => '^18.2.0', 'karma' => '~6.4.0', 'karma-chrome-launcher' => '~3.2.0', 'tslint' => '~6.1.3', 'protractor' => '~7.0.0',
        ]],
        'karma.conf.js' => "module.exports = config => config.set({ browsers: ['Chrome'], customLaunchers: { ChromeHeadlessCI: { base: 'ChromeHeadless', flags: ['--no-sandbox'] } } });\n",
        'src/app/loan.component.ts' => "@Component({ selector: 'app-loan', template: '' })\nexport class LoanComponent {}\n",
        'external-libraries/money/package.json' => ['name' => 'money-format'],
        'external-libraries/money/fesm2015/money.mjs' => "export {};\n",
    ]);

    $scan = frontendCall('larapilot:frontend-scan', ['--path' => $root])['data'];
    $loans = $scan['target_projects'][0];
    $warnings = implode("\n", $scan['warnings']);

    expect($loans['commands']['test'])->toBe('CI=true npx ng test loans --watch=false --browsers=ChromeHeadlessCI')
        ->and($loans['commands'])->not->toHaveKey('lint')
        ->and($loans['commands']['e2e'])->toBe('CI=true npx ng e2e loans')
        ->and($loans['unavailable']['lint'])->toContain('TSLint support left the Angular CLI in v13')
        ->and($loans['tests'])->toBe(['spec_files' => 0, 'generators_skip' => true, 'runnable' => true])
        ->and($scan['write_scope']['vendored'])->toBe([['path' => 'external-libraries/money', 'package' => 'money-format']])
        ->and($warnings)->toContain('Target lint of loans cannot run')
        ->and($warnings)->toContain('skipTests=true');

    // Installed, the package's own catalog decides.
    frontendWrite($root, [
        'node_modules/@angular-devkit/build-angular/package.json' => ['name' => '@angular-devkit/build-angular', 'version' => '19.0.0', 'builders' => './builders.json'],
        'node_modules/@angular-devkit/build-angular/builders.json' => ['builders' => ['browser' => [], 'karma' => []]],
    ]);

    $installed = frontendCall('larapilot:frontend-scan', ['--path' => $root])['data']['target_projects'][0];

    expect($installed['unavailable']['e2e'])->toContain('has no protractor builder')
        ->and($installed['commands'])->not->toHaveKey('e2e');
});
