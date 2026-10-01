<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Larapilot\Services\ProjectStackService;
use Larapilot\Services\Upgrade\PackagistClient;
use Larapilot\Support\Cvss;
use Larapilot\Support\SupportPolicy;

/**
 * Files the tests of this suite write at the root of the sandbox, put back
 * as they were afterwards.
 */
const UPGRADE_FIXTURE_FILES = ['composer.json', 'composer.lock', 'Dockerfile', 'docker-compose.yml', '.github/workflows/upgrade-fixture.yml', 'app/LarapilotUpgradeFixture/Legacy.php', 'app/LarapilotUpgradeFixture/Report.php'];

beforeEach(function (): void {
    $this->upgradeBackup = [];

    foreach (UPGRADE_FIXTURE_FILES as $file) {
        $path = base_path($file);
        $this->upgradeBackup[$file] = is_file($path) ? (string) file_get_contents($path) : null;
    }
});

afterEach(function (): void {
    foreach ($this->upgradeBackup as $file => $contents) {
        $path = base_path($file);

        if ($contents === null) {
            if (is_file($path)) {
                unlink($path);
            }
        } else {
            file_put_contents($path, $contents);
        }
    }

    foreach (['app/LarapilotUpgradeFixture', '.github/workflows', '.github'] as $folder) {
        $path = base_path($folder);

        if (is_dir($path) && (scandir($path) ?: []) === ['.', '..']) {
            rmdir($path);
        }
    }
});

/**
 * A project on Laravel 12 with Filament 3, Spatie Backup 9, an abandoned
 * package, and a package that stops at PHP 8.3.
 *
 * @param  array<string, mixed>  $extraRequire
 */
function upgradeProject(array $extraRequire = []): void
{
    file_put_contents(base_path('composer.json'), json_encode([
        'name' => 'acme/shop',
        'description' => 'The shop.',
        'require' => array_merge([
            'php' => '^8.2',
            'laravel/framework' => '^12.0',
            'filament/filament' => '^3.2',
            'spatie/laravel-backup' => '^9.0',
            'old/abandoned-pkg' => '^1.0',
            'legacy/php-locked' => '^1.0',
        ], $extraRequire),
        'require-dev' => [
            'pestphp/pest' => '^3.0',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    $package = static fn (string $name, string $version, array $require, array $extra = []): array => array_merge([
        'name' => $name,
        'version' => $version,
        'version_normalized' => ltrim($version, 'v').'.0',
        'type' => 'library',
        'license' => ['MIT'],
        'require' => $require,
        'description' => $name,
    ], $extra);

    file_put_contents(base_path('composer.lock'), json_encode([
        'content-hash' => 'fixture',
        'packages' => [
            $package('laravel/framework', 'v12.10.0', ['php' => '^8.2']),
            $package('filament/filament', 'v3.3.0', ['php' => '^8.1', 'filament/support' => 'self.version']),
            $package('filament/support', 'v3.3.0', ['php' => '^8.1', 'illuminate/support' => '^10.45|^11.0|^12.0']),
            $package('spatie/laravel-backup', '9.2.0', ['php' => '^8.2', 'illuminate/console' => '^10.10|^11.0|^12.0']),
            $package('old/abandoned-pkg', '1.0.0', ['php' => '>=8.0', 'illuminate/support' => '^10.0|^11.0'], ['abandoned' => 'new/pkg']),
            $package('legacy/php-locked', '1.0.0', ['php' => '>=7.4 <8.4']),
            $package('guzzlehttp/psr7', '2.4.0', ['php' => '^7.2.5 || ^8.0']),
        ],
        'packages-dev' => [
            $package('pestphp/pest', 'v3.0.0', ['php' => '^8.2']),
        ],
        'platform' => ['php' => '^8.2'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Packagist answers in the minified format of Composer 2: each version
 * after the first lists only what changed, and `__unset` removes a key.
 */
function fakePackagist(): void
{
    $p2 = static fn (string $name, array $versions): array => ['minified' => 'composer/2.0', 'packages' => [$name => $versions]];

    Http::fake([
        'repo.packagist.org/p2/filament/filament.json' => Http::response($p2('filament/filament', [
            ['name' => 'filament/filament', 'version' => 'v4.0.0', 'version_normalized' => '4.0.0.0', 'require' => ['php' => '^8.2', 'filament/support' => 'self.version']],
            ['version' => 'v4.0.0-beta1', 'version_normalized' => '4.0.0.0-beta1'],
            ['version' => 'v3.3.0', 'version_normalized' => '3.3.0.0', 'require' => ['php' => '^8.1', 'filament/support' => 'self.version']],
        ])),
        'repo.packagist.org/p2/filament/support.json' => Http::response($p2('filament/support', [
            ['name' => 'filament/support', 'version' => 'v4.0.0', 'version_normalized' => '4.0.0.0', 'require' => ['php' => '^8.2', 'illuminate/support' => '^11.28|^12.0|^13.0']],
            ['version' => 'v3.3.0', 'version_normalized' => '3.3.0.0', 'require' => ['php' => '^8.1', 'illuminate/support' => '^10.45|^11.0|^12.0']],
        ])),
        'repo.packagist.org/p2/spatie/laravel-backup.json' => Http::response($p2('spatie/laravel-backup', [
            ['name' => 'spatie/laravel-backup', 'version' => '10.0.0', 'version_normalized' => '10.0.0.0', 'require' => ['php' => '^8.3', 'illuminate/console' => '^12.0|^13.0'], 'funding' => [['type' => 'github']]],
            ['version' => '9.2.0', 'version_normalized' => '9.2.0.0', 'require' => ['php' => '^8.2', 'illuminate/console' => '^10.10|^11.0|^12.0'], 'funding' => '__unset'],
        ])),
        'repo.packagist.org/*' => Http::response('', 404),
    ]);
}

/**
 * @param  array<string, mixed>  $parameters
 * @return array<string, mixed>
 */
function upgradeCheck(array $parameters): array
{
    expect(Artisan::call('larapilot:upgrade-check', $parameters))->toBeIn([0, 1]);

    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['kind'] ?? null)->toBe('upgrade_check', (string) Artisan::output());

    return $envelope['data'];
}

it('knows where each version stands in its support window', function (): void {
    expect(SupportPolicy::status('laravel', '12.10.0', '2026-10-01'))
        ->toMatchArray(['cycle' => '12', 'state' => 'security', 'security_until' => '2027-02-24', 'ending' => true, 'latest' => '13', 'is_latest' => false])
        ->and(SupportPolicy::status('laravel', '13.2', '2026-10-01'))->toMatchArray(['state' => 'active', 'is_latest' => true])
        ->and(SupportPolicy::status('php', '8.1.30', '2026-10-01')['state'])->toBe('eol')
        ->and(SupportPolicy::status('php', '8.4', '2026-10-01')['state'])->toBe('active')
        ->and(SupportPolicy::status('mysql', '8.0.36', '2026-10-01')['state'])->toBe('eol')
        ->and(SupportPolicy::status('mysql', '8.4.2', '2026-10-01')['state'])->toBe('active')
        ->and(SupportPolicy::status('postgres', '17.2', '2026-10-01'))->toMatchArray(['product' => 'pgsql', 'cycle' => '17', 'state' => 'active'])
        // A version newer than the table is unknown, never end of life.
        ->and(SupportPolicy::status('laravel', '14', '2026-10-01')['state'])->toBe('unknown')
        ->and(SupportPolicy::cycle('mariadb', '10.11.6'))->toBe('10.11')
        ->and(SupportPolicy::laravelPhpRange('13'))->toBe(['8.3', '8.5']);
});

it('reads the engine behind a server version string', function (): void {
    expect(ProjectStackService::parseServerVersion('mysql', '5.5.5-10.11.6-MariaDB-1:10.11.6+maria~ubu2204'))->toBe(['mariadb', '10.11.6'])
        ->and(ProjectStackService::parseServerVersion('mysql', '8.4.2'))->toBe(['mysql', '8.4.2'])
        ->and(ProjectStackService::parseServerVersion('pgsql', '17.2 (Debian 17.2-1.pgdg120+1)'))->toBe(['pgsql', '17.2']);
});

it('scores a CVSS 3 vector as FIRST specifies it', function (): void {
    expect(Cvss::score('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:L/A:N'))->toBe(5.3)
        ->and(Cvss::score('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'))->toBe(9.8)
        ->and(Cvss::score('CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N'))->toBe(6.1)
        ->and(Cvss::score('CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N'))->toBeNull()
        ->and(Cvss::severity(9.8))->toBe('critical')
        ->and(Cvss::severity(5.3))->toBe('medium');
});

it('expands the minified metadata of Packagist the way Composer does', function (): void {
    $expanded = PackagistClient::expand([
        ['name' => 'a/b', 'version' => '2.0.0', 'require' => ['php' => '^8.2'], 'funding' => ['x']],
        ['version' => '1.0.0', 'funding' => '__unset'],
    ]);

    expect($expanded[1])->toBe(['name' => 'a/b', 'version' => '1.0.0', 'require' => ['php' => '^8.2']]);
});

it('tells what the project runs on, from the lock and the files around it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    upgradeProject();
    file_put_contents(base_path('Dockerfile'), "FROM php:8.2-fpm-alpine\nRUN docker-php-ext-install pdo_mysql\n");

    expect(Artisan::call('larapilot:stack'))->toBe(0);
    $data = json_decode(Artisan::output(), true)['data'];

    expect($data['laravel'])->toMatchArray(['version' => '12.10.0', 'constraint' => '^12.0', 'major' => '12'])
        ->and($data['laravel']['support']['state'])->toBeIn(['active', 'security', 'eol'])
        ->and($data['php']['constraint'])->toBe('^8.2')
        ->and($data['project']['package'])->toBe('acme/shop')
        ->and(array_column($data['packages'], 'name'))->toContain('filament/filament', 'pestphp/pest')
        ->and($data['database']['reachable'])->toBeTrue()
        ->and($data['database']['engine'])->toBe('sqlite')
        ->and($data['pins'])->toContain(['file' => 'Dockerfile', 'line' => 1, 'kind' => 'php', 'value' => '8.2', 'text' => 'FROM php:8.2-fpm-alpine']);

    // Alerts name the tracked packages only.
    expect(collect($data['alerts'])->pluck('message')->implode(' '))->not->toContain('old/abandoned-pkg');

    expect(Artisan::call('larapilot:stack', ['--only' => 'php,laravel', '--no-db' => true]))->toBe(0);
    $only = json_decode(Artisan::output(), true)['data'];

    expect(array_keys($only))->toBe(['php', 'laravel', 'support_table', 'alerts']);

    expect(Artisan::call('larapilot:stack', ['--only' => 'php,nothing']))->toBe(2)
        ->and(Artisan::output())->toContain('Unknown section: nothing');
});

it('checks a Laravel upgrade against what Packagist publishes', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    upgradeProject();
    fakePackagist();

    $check = upgradeCheck(['--laravel' => '13', '--report' => true]);
    $dependencies = collect($check['laravel']['dependencies'])->keyBy('name');

    expect($check['laravel']['steps'])->toHaveCount(1)
        ->and($check['laravel']['steps'][0])->toMatchArray(['from' => '12', 'to' => '13', 'php' => ['8.3', '8.5'], 'guide' => 'https://laravel.com/docs/13.x/upgrade'])
        // Filament leaves Laravel to filament/support: the check follows it.
        ->and($dependencies['filament/filament'])->toMatchArray(['verdict' => 'bump', 'candidate' => '4.0.0'])
        // Searched on PHP 8.3, the floor Laravel 13 needs, not on the project's 8.2.
        ->and($dependencies['spatie/laravel-backup'])->toMatchArray(['verdict' => 'bump', 'candidate' => '10.0.0'])
        ->and($dependencies['old/abandoned-pkg']['verdict'])->toBe('abandoned')
        ->and($dependencies['old/abandoned-pkg']['action'])->toContain('new/pkg')
        // Not tied to Laravel: counted, not listed.
        ->and($dependencies->has('legacy/php-locked'))->toBeFalse()
        ->and($check['laravel']['agnostic'])->toBe(2)
        ->and($check['laravel']['commands'][1])->toBe('composer require laravel/framework:^13.0 filament/filament:^4.0 spatie/laravel-backup:^10.0 --with-all-dependencies --dry-run');

    $php = collect($check['criticalities'])->firstWhere('area', 'php');

    expect($php['message'])->toContain('Laravel 13 needs PHP 8.3')
        ->and($php['level'])->toBeIn(['high', 'blocker'])
        ->and($check['verdict'])->toBeIn(['attention', 'blocked'])
        ->and($check['report'])->toStartWith('.larapilot/docs/upgrades/')
        ->and(base_path($check['report']))->toBeFile()
        ->and((string) file_get_contents(base_path($check['report'])))->toContain('# Upgrade readiness — Laravel 12.10.0 → 13', 'filament/filament', 'composer why-not laravel/framework 13.0');

    // The answers are cached on disk: a second check asks Packagist nothing.
    $asked = count(Http::recorded());

    expect(upgradeCheck(['--laravel' => '13'])['warnings'])->toBe([])
        ->and(count(Http::recorded()))->toBe($asked);
});

it('reads the lock only when offline, and refuses what it cannot check', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    upgradeProject();
    Http::preventStrayRequests();

    $check = upgradeCheck(['--laravel' => '13', '--offline' => true]);

    expect(collect($check['laravel']['dependencies'])->firstWhere('name', 'filament/filament')['verdict'])->toBe('unknown');

    expect(Artisan::call('larapilot:upgrade-check', ['--laravel' => '12']))->toBe(2)
        ->and(Artisan::output())->toContain('name a newer major than 12');

    expect(Artisan::call('larapilot:upgrade-check', ['--laravel' => 'latest']))->toBe(2);
    expect(Artisan::call('larapilot:upgrade-check'))->toBe(2)
        ->and(Artisan::output())->toContain('Name a target');
    expect(Artisan::call('larapilot:upgrade-check', ['--db' => 'oracle:19']))->toBe(2);
});

it('checks a PHP upgrade: packages, pins, code, and extensions', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    upgradeProject(['ext-imap' => '*']);
    fakePackagist();

    file_put_contents(base_path('Dockerfile'), "FROM php:8.2-fpm\n");
    @mkdir(base_path('.github/workflows'), 0755, true);
    file_put_contents(base_path('.github/workflows/upgrade-fixture.yml'), "jobs:\n  tests:\n    steps:\n      - uses: shivammathur/setup-php@v2\n        with:\n          php-version: '8.2'\n");
    @mkdir(base_path('app/LarapilotUpgradeFixture'), 0755, true);
    file_put_contents(base_path('app/LarapilotUpgradeFixture/Legacy.php'), "<?php\n\nclass Legacy\n{\n    public function handle(Request \$request = null, ?Foo \$ok = null)\n    {\n        return \"Hello \${name}\";\n    }\n}\n");

    $check = upgradeCheck(['--php' => '8.4']);
    $messages = collect($check['criticalities'])->pluck('message')->implode("\n");

    expect($check['current']['php'])->toMatchArray(['project' => '8.2', 'source' => 'constraint'])
        ->and($check['php']['from'])->toBe('8.2')
        ->and(collect($check['php']['packages'])->firstWhere('name', 'legacy/php-locked'))->toMatchArray(['requires_php' => '>=7.4 <8.4', 'verdict' => 'private'])
        ->and(array_column($check['php']['pins_behind'], 'file'))->toContain('Dockerfile', '.github/workflows/upgrade-fixture.yml')
        ->and(array_column($check['php']['code']['findings'], 'id'))->toContain('php84-implicit-nullable')
        // Already on 8.2: its deprecations are not news.
        ->and(array_column($check['php']['code']['findings'], 'id'))->not->toContain('php82-dollar-brace')
        ->and($check['php']['extensions_removed'])->toBe(['imap'])
        ->and($messages)->toContain('ext-imap left PHP core in 8.4')
        ->and($check['php']['guides'])->toBe([
            'https://www.php.net/manual/en/migration83.php',
            'https://www.php.net/manual/en/migration84.php',
        ]);

    // The implicitly nullable parameter is found; the explicit one is not.
    $nullable = collect($check['php']['code']['findings'])->firstWhere('id', 'php84-implicit-nullable');

    expect($nullable['count'])->toBe(1)
        ->and($nullable['occurrences'][0])->toMatchArray(['file' => 'app/LarapilotUpgradeFixture/Legacy.php', 'line' => 5]);
});

it('checks a database switch and a MySQL upgrade against the code', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    upgradeProject();
    @mkdir(base_path('app/LarapilotUpgradeFixture'), 0755, true);
    file_put_contents(base_path('app/LarapilotUpgradeFixture/Report.php'), "<?php\n\n\$rows = DB::select(\"SELECT GROUP_CONCAT(name) FROM `users` WHERE IFNULL(active, 0) = 1\");\n\$users = User::where('name', 'like', \"%{\$q}%\")->get();\n");
    file_put_contents(base_path('docker-compose.yml'), "services:\n  mysql:\n    image: 'mysql/mysql-server:8.0'\n    command: --default-authentication-plugin=mysql_native_password\n");

    $switch = upgradeCheck(['--db' => 'pgsql:17', '--db-from' => 'mysql:8.0']);
    $ids = array_column($switch['database']['scan']['findings'], 'id');

    expect($switch['database']['kind'])->toBe('switch')
        ->and($switch['database']['from'])->toMatchArray(['engine' => 'mysql', 'version' => '8.0'])
        ->and($ids)->toContain('mysql-functions', 'mysql-backticks', 'like-case')
        ->and(collect($switch['database']['scan']['checklist'])->pluck('title'))->toContain('Reset the sequences after the copy')
        ->and(collect($switch['criticalities'])->pluck('message'))->toContain('Reset the sequences after the copy.')
        // The MySQL image of the compose file must move with the engine.
        ->and(collect($switch['criticalities'])->where('area', 'pin')->pluck('message')->implode(' '))->toContain('docker-compose.yml:3');

    $upgrade = upgradeCheck(['--db' => 'mysql:8.4', '--db-from' => 'mysql:8.0']);

    expect($upgrade['database']['kind'])->toBe('upgrade')
        ->and(array_column($upgrade['database']['scan']['findings'], 'id'))->toContain('mysql84-native-password')
        ->and($upgrade['verdict'])->toBe('blocked')
        ->and($upgrade['counts']['blocker'])->toBe(1);
});
