<?php

declare(strict_types=1);

namespace Larapilot\Services;

use Illuminate\Support\Facades\DB;
use Larapilot\LarapilotServiceProvider;
use Larapilot\Services\Stack\VersionPins;
use Larapilot\Support\ComposerFiles;
use Larapilot\Support\SupportPolicy;

/**
 * What the project runs on: PHP, Laravel, the database, the drivers, the
 * packages that shape an upgrade (admin panels, frontend stacks, first-party
 * services), the frontend, the tooling, and the files that pin a version.
 * Each version comes with where it stands in its upstream support window.
 *
 * Read by `larapilot:stack`, the About page of the dashboard, and the
 * upgrade checks. Nothing here changes the project.
 */
class ProjectStackService
{
    public const SECTIONS = ['project', 'php', 'laravel', 'database', 'drivers', 'packages', 'frontend', 'tooling', 'pins'];

    /**
     * Packages worth naming on their own, with what they are to the project.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const KNOWN_PACKAGES = [
        'filament/filament' => ['Filament', 'Admin panel'],
        'laravel/nova' => ['Nova', 'Admin panel'],
        'backpack/crud' => ['Backpack', 'Admin panel'],
        'orchid/platform' => ['Orchid', 'Admin panel'],
        'moonshine/moonshine' => ['MoonShine', 'Admin panel'],
        'livewire/livewire' => ['Livewire', 'Frontend'],
        'livewire/volt' => ['Volt', 'Frontend'],
        'livewire/flux' => ['Flux', 'Frontend'],
        'inertiajs/inertia-laravel' => ['Inertia', 'Frontend'],
        'laravel/folio' => ['Folio', 'Frontend'],
        'laravel/horizon' => ['Horizon', 'Queues'],
        'laravel/octane' => ['Octane', 'Server'],
        'laravel/reverb' => ['Reverb', 'WebSockets'],
        'laravel/pulse' => ['Pulse', 'Monitoring'],
        'laravel/nightwatch' => ['Nightwatch', 'Monitoring'],
        'laravel/telescope' => ['Telescope', 'Debugging'],
        'barryvdh/laravel-debugbar' => ['Debugbar', 'Debugging'],
        'laravel/sanctum' => ['Sanctum', 'Auth'],
        'laravel/passport' => ['Passport', 'Auth'],
        'laravel/fortify' => ['Fortify', 'Auth'],
        'laravel/jetstream' => ['Jetstream', 'Auth'],
        'laravel/breeze' => ['Breeze', 'Auth'],
        'laravel/socialite' => ['Socialite', 'Auth'],
        'spatie/laravel-permission' => ['Laravel Permission', 'Auth'],
        'laravel/cashier' => ['Cashier (Stripe)', 'Billing'],
        'laravel/cashier-paddle' => ['Cashier (Paddle)', 'Billing'],
        'laravel/scout' => ['Scout', 'Search'],
        'laravel/pennant' => ['Pennant', 'Feature flags'],
        'stancl/tenancy' => ['Tenancy for Laravel', 'Tenancy'],
        'spatie/laravel-multitenancy' => ['Laravel Multitenancy', 'Tenancy'],
        'spatie/laravel-medialibrary' => ['Media Library', 'Media'],
        'spatie/laravel-backup' => ['Laravel Backup', 'Operations'],
        'spatie/laravel-activitylog' => ['Activity Log', 'Operations'],
        'spatie/laravel-data' => ['Laravel Data', 'Data'],
        'maatwebsite/excel' => ['Laravel Excel', 'Data'],
        'laravel/vapor-core' => ['Vapor', 'Deploy'],
        'laravel/sail' => ['Sail', 'Local dev'],
        'laravel/boost' => ['Boost', 'AI'],
        'laravel/mcp' => ['MCP', 'AI'],
        'laravel/ai' => ['AI SDK', 'AI'],
        'sentry/sentry-laravel' => ['Sentry', 'Errors'],
        'bugsnag/bugsnag-laravel' => ['Bugsnag', 'Errors'],
        'spatie/laravel-ignition' => ['Ignition', 'Errors'],
        'nesbot/carbon' => ['Carbon', 'Dates'],
        'doctrine/dbal' => ['Doctrine DBAL', 'Database'],
        'pestphp/pest' => ['Pest', 'Tests'],
        'phpunit/phpunit' => ['PHPUnit', 'Tests'],
        'laravel/dusk' => ['Dusk', 'Tests'],
        'laravel/pint' => ['Pint', 'Quality'],
        'larastan/larastan' => ['Larastan', 'Quality'],
        'nunomaduro/larastan' => ['Larastan (old name)', 'Quality'],
        'phpstan/phpstan' => ['PHPStan', 'Quality'],
        'rector/rector' => ['Rector', 'Quality'],
        'driftingly/rector-laravel' => ['Rector Laravel', 'Quality'],
        'andreapollastri/checkpoint' => ['Checkpoint', 'Security'],
        'andreapollastri/larapilot' => ['Larapilot', 'Workflow'],
    ];

    /**
     * PHP extensions an upgrade or a new server most often trips on.
     *
     * @var list<string>
     */
    public const EXTENSIONS = ['pdo_mysql', 'pdo_pgsql', 'pdo_sqlite', 'mbstring', 'intl', 'bcmath', 'gd', 'imagick', 'redis', 'zip', 'curl', 'openssl', 'sodium', 'opcache', 'pcntl', 'exif', 'xml', 'fileinfo'];

    public function __construct(
        protected ConfigService $config,
        protected GitService $git,
        protected VersionPins $pins,
    ) {}

    /**
     * @param  list<string>|null  $only
     * @return array<string, mixed>
     */
    public function facts(?array $only = null, bool $probeDatabase = true): array
    {
        $only = $only === null || $only === [] ? self::SECTIONS : array_values(array_intersect(self::SECTIONS, $only));
        $composer = $this->composer();
        $facts = [];

        foreach ($only as $section) {
            $facts[$section] = match ($section) {
                'project' => $this->project($composer),
                'php' => $this->php($composer),
                'laravel' => $this->laravel($composer),
                'database' => $this->database($probeDatabase),
                'drivers' => $this->drivers(),
                'packages' => $this->packages($composer),
                'frontend' => $this->frontend(),
                'tooling' => $this->tooling(),
                'pins' => $this->pins->scan($this->root()),
            };
        }

        $facts['support_table'] = SupportPolicy::CHECKED;
        $facts['alerts'] = $this->alerts($facts);

        return $facts;
    }

    public function root(): string
    {
        return rtrim($this->config->projectRoot(), '/');
    }

    public function composer(): ComposerFiles
    {
        return new ComposerFiles($this->root());
    }

    /**
     * @return array<string, mixed>
     */
    protected function project(ComposerFiles $composer): array
    {
        return [
            'name' => (string) config('app.name', 'Laravel'),
            'package' => $composer->name(),
            'description' => $composer->description(),
            'environment' => (string) app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => (string) config('app.url', ''),
            'timezone' => (string) config('app.timezone', 'UTC'),
            'locale' => (string) config('app.locale', 'en'),
            'maintenance' => app()->isDownForMaintenance(),
            'larapilot' => LarapilotServiceProvider::VERSION,
            'composer_lock' => $composer->hasLock(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function php(ComposerFiles $composer): array
    {
        $running = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION;
        $extensions = [];

        foreach (self::EXTENSIONS as $extension) {
            $extensions[$extension] = extension_loaded($extension);
        }

        return [
            'running' => $running,
            'sapi' => PHP_SAPI,
            'constraint' => $composer->phpConstraint(),
            'platform' => $composer->platformPhp(),
            'lock_platform' => $composer->lockPlatformPhp(),
            'support' => SupportPolicy::status('php', $running),
            'extensions' => $extensions,
            'ini' => [
                'memory_limit' => (string) ini_get('memory_limit'),
                'max_execution_time' => (string) ini_get('max_execution_time'),
                'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'post_max_size' => (string) ini_get('post_max_size'),
                'opcache' => function_exists('opcache_get_status') && (bool) ini_get('opcache.enable'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function laravel(ComposerFiles $composer): array
    {
        $version = $composer->version('laravel/framework') ?? $this->runningLaravel();
        $direct = $composer->direct()['laravel/framework'] ?? null;
        $major = SupportPolicy::cycle('laravel', $version);
        $range = $major !== null ? SupportPolicy::laravelPhpRange($major) : null;
        $php = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

        return [
            'version' => $version,
            'running' => $this->runningLaravel(),
            'constraint' => $direct['constraint'] ?? null,
            'major' => $major,
            'support' => SupportPolicy::status('laravel', $version),
            'php_range' => $range,
            'php_supported' => $range === null ? null : version_compare($php, $range[0], '>=') && version_compare($php, $range[1], '<='),
        ];
    }

    protected function runningLaravel(): string
    {
        return ltrim((string) app()->version(), 'v');
    }

    /**
     * The default connection, the others by driver, and the version of the
     * server when it answers. The probe opens its own short-lived connection,
     * with a three-second timeout, and never fails the caller.
     *
     * @return array<string, mixed>
     */
    public function database(bool $probe = true): array
    {
        $default = (string) config('database.default', '');
        $connections = is_array(config('database.connections')) ? config('database.connections') : [];
        $settings = is_array($connections[$default] ?? null) ? $connections[$default] : [];
        $driver = (string) ($settings['driver'] ?? '');

        $data = [
            'default' => $default,
            'driver' => $driver,
            'database' => $this->databaseName($settings),
            'host' => in_array($driver, ['sqlite'], true) ? null : (is_string($settings['host'] ?? null) ? $settings['host'] : null),
            'port' => in_array($driver, ['sqlite'], true) ? null : ($settings['port'] ?? null),
            'charset' => $settings['charset'] ?? null,
            'collation' => $settings['collation'] ?? null,
            'connections' => array_map(
                static fn (mixed $connection): string => is_array($connection) ? (string) ($connection['driver'] ?? '') : '',
                $connections
            ),
            'reachable' => null,
            'server' => null,
            'engine' => $this->engineOf($driver),
            'version' => null,
            'support' => null,
            'error' => null,
        ];

        if ($probe && $driver !== '') {
            $probed = $this->probe($default, $settings);
            $data = array_merge($data, $probed);
        }

        if ($data['engine'] !== null && $data['engine'] !== 'sqlite' && $data['version'] !== null) {
            $data['support'] = SupportPolicy::status($data['engine'], $data['version']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{reachable: bool, server: string|null, engine: string|null, version: string|null, error: string|null}
     */
    protected function probe(string $name, array $settings): array
    {
        $probe = 'larapilot_stack_probe';
        $driver = (string) ($settings['driver'] ?? '');

        // Laravel's Postgres DSN carries no `connect_timeout`; every PDO
        // driver here reads ATTR_TIMEOUT as the seconds to wait for the server.
        if (in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            $settings['options'] = (is_array($settings['options'] ?? null) ? $settings['options'] : []) + [\PDO::ATTR_TIMEOUT => 3];
        }

        config(['database.connections.'.$probe => $settings]);

        try {
            $server = (string) DB::connection($probe)->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            [$engine, $version] = self::parseServerVersion($driver, $server);

            return ['reachable' => true, 'server' => $server, 'engine' => $engine, 'version' => $version, 'error' => null];
        } catch (\Throwable $e) {
            return ['reachable' => false, 'server' => null, 'engine' => $this->engineOf($driver), 'version' => null, 'error' => $this->safeError($e->getMessage())];
        } finally {
            try {
                DB::purge($probe);
            } catch (\Throwable) {
                // nothing was opened
            }

            // Unset, not null: a null entry would stay in the list of
            // connections for the rest of a long-lived (Octane) process.
            config()->offsetUnset('database.connections.'.$probe);
        }
    }

    /**
     * The engine and the version a server reports: MariaDB says it is
     * MariaDB in the version string, sometimes behind a `5.5.5-` prefix.
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function parseServerVersion(string $driver, string $server): array
    {
        if (stripos($server, 'mariadb') !== false) {
            $clean = (string) preg_replace('/^5\.5\.5-/', '', $server);

            return ['mariadb', preg_match('/(\d+\.\d+(?:\.\d+)?)/', $clean, $m) === 1 ? $m[1] : null];
        }

        $version = preg_match('/(\d+\.\d+(?:\.\d+)?)/', $server, $m) === 1 ? $m[1] : (preg_match('/(\d+)/', $server, $n) === 1 ? $n[1] : null);

        return match ($driver) {
            'mysql' => ['mysql', $version],
            'mariadb' => ['mariadb', $version],
            'pgsql' => ['pgsql', $version],
            'sqlite' => ['sqlite', $version],
            'sqlsrv' => ['sqlsrv', $version],
            default => [$driver !== '' ? $driver : null, $version],
        };
    }

    protected function engineOf(string $driver): ?string
    {
        return $driver === '' ? null : $driver;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function databaseName(array $settings): ?string
    {
        $database = $settings['database'] ?? null;

        if (! is_string($database) || $database === '') {
            return null;
        }

        // A SQLite file is named by its file, never by the path on this machine.
        if (($settings['driver'] ?? '') === 'sqlite') {
            return $database === ':memory:' ? ':memory:' : basename($database);
        }

        return $database;
    }

    /**
     * A connection error without the password or the DSN it may carry.
     */
    protected function safeError(string $message): string
    {
        $message = (string) preg_replace('/(password|pwd)=[^;\s)]*/i', '$1=***', $message);
        $message = (string) preg_replace('/\(Connection: .*$/s', '', $message);

        return mb_substr(trim($message), 0, 240);
    }

    /**
     * @return array<string, string|null>
     */
    protected function drivers(): array
    {
        return [
            'cache' => $this->configString('cache.default'),
            'queue' => $this->configString('queue.default'),
            'session' => $this->configString('session.driver'),
            'mail' => $this->configString('mail.default'),
            'filesystem' => $this->configString('filesystems.default'),
            'broadcast' => $this->configString('broadcasting.default'),
            'log' => $this->configString('logging.default'),
            'scout' => $this->configString('scout.driver'),
            'octane' => $this->configString('octane.server'),
        ];
    }

    protected function configString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The packages of {@see self::KNOWN_PACKAGES} the project installed.
     *
     * @return list<array{name: string, label: string, role: string, version: string, constraint: string|null, dev: bool, direct: bool, abandoned: bool|string}>
     */
    protected function packages(ComposerFiles $composer): array
    {
        $direct = $composer->direct();
        $packages = [];

        foreach (self::KNOWN_PACKAGES as $name => [$label, $role]) {
            $installed = $composer->package($name);

            if ($installed === null) {
                continue;
            }

            $packages[] = [
                'name' => $name,
                'label' => $label,
                'role' => $role,
                'version' => (string) $installed['version'],
                'constraint' => $direct[$name]['constraint'] ?? null,
                'dev' => (bool) $installed['dev'],
                'direct' => isset($direct[$name]),
                'abandoned' => $installed['abandoned'],
            ];
        }

        return $packages;
    }

    /**
     * The frontend inside this repository, and the external one when the
     * companion links it.
     *
     * @return array<string, mixed>
     */
    protected function frontend(): array
    {
        $root = $this->root();
        $package = $this->decodeJson($root.'/package.json');
        $dependencies = array_merge(
            is_array($package['dependencies'] ?? null) ? $package['dependencies'] : [],
            is_array($package['devDependencies'] ?? null) ? $package['devDependencies'] : []
        );

        $node = null;

        foreach (['.nvmrc', '.node-version'] as $file) {
            if (is_file($root.'/'.$file)) {
                $node = trim((string) file_get_contents($root.'/'.$file));
                break;
            }
        }

        $node ??= is_string($package['engines']['node'] ?? null) ? $package['engines']['node'] : null;
        $node ??= is_string($package['volta']['node'] ?? null) ? $package['volta']['node'] : null;

        $frameworks = [];

        foreach (['vue' => 'Vue', 'react' => 'React', 'svelte' => 'Svelte', 'alpinejs' => 'Alpine.js', '@inertiajs/vue3' => 'Inertia (Vue)', '@inertiajs/react' => 'Inertia (React)', '@inertiajs/svelte' => 'Inertia (Svelte)', 'tailwindcss' => 'Tailwind CSS', 'bootstrap' => 'Bootstrap', 'typescript' => 'TypeScript', 'vite' => 'Vite', 'laravel-mix' => 'Laravel Mix'] as $name => $label) {
            if (isset($dependencies[$name])) {
                $frameworks[] = ['name' => $name, 'label' => $label, 'constraint' => (string) $dependencies[$name]];
            }
        }

        $companion = $this->config->frontend();

        return [
            'package_json' => $package !== null,
            'package_manager' => $this->packageManager($root, $package),
            'node' => $node,
            'node_running' => null,
            // `engines.node` is usually a floor or a range (`>=18`, `^20 || ^22`):
            // only an exact pin names the version the project runs on.
            'node_support' => $node !== null && preg_match('/^v?\d+(\.\d+)*$|^lts\//i', $node) === 1 ? SupportPolicy::status('node', $node) : null,
            'stack' => $frameworks,
            'dependencies' => count($dependencies),
            'companion' => [
                'configured' => (bool) $companion['configured'],
                'stack' => $companion['stack'],
                'mode' => $companion['mode'],
                'projects' => $companion['projects'],
                // The folder name only: the path on this machine stays out.
                'repository' => is_string($companion['repo_path']) && $companion['repo_path'] !== '' ? basename(rtrim($companion['repo_path'], '/')) : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $package
     */
    public function packageManager(string $root, ?array $package = null): ?string
    {
        $declared = is_string($package['packageManager'] ?? null) ? $package['packageManager'] : '';

        if ($declared !== '' && preg_match('/^(npm|pnpm|yarn|bun)@/', $declared, $m) === 1) {
            return $m[1];
        }

        return match (true) {
            is_file($root.'/pnpm-lock.yaml') => 'pnpm',
            is_file($root.'/yarn.lock') => 'yarn',
            is_file($root.'/bun.lock'), is_file($root.'/bun.lockb') => 'bun',
            is_file($root.'/package-lock.json') => 'npm',
            $package !== null => 'npm',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function tooling(): array
    {
        $root = $this->root();
        $git = $this->git->isRepository();
        $commit = $git ? $this->git->read('log', '-1', '--format=%h%x09%s%x09%cI') : null;
        $parts = is_string($commit) && $commit !== '' ? explode("\t", trim($commit), 3) : [];

        $ci = [];

        foreach ([
            '.github/workflows' => 'GitHub Actions',
            '.gitlab-ci.yml' => 'GitLab CI',
            'bitbucket-pipelines.yml' => 'Bitbucket Pipelines',
            'azure-pipelines.yml' => 'Azure Pipelines',
            '.circleci' => 'CircleCI',
        ] as $path => $label) {
            if (file_exists($root.'/'.$path)) {
                $ci[] = $label;
            }
        }

        $deploy = [];

        foreach ([
            'vapor.yml' => 'Laravel Vapor',
            'Dockerfile' => 'Docker',
            'fly.toml' => 'Fly.io',
            'nixpacks.toml' => 'Nixpacks',
            'render.yaml' => 'Render',
            'Procfile' => 'Procfile (Heroku-style)',
            'herd.yml' => 'Laravel Herd',
            'envoy.blade.php' => 'Envoy',
            'deploy.php' => 'Deployer',
        ] as $file => $label) {
            if (is_file($root.'/'.$file)) {
                $deploy[] = $label;
            }
        }

        foreach (['docker-compose.yml', 'docker-compose.yaml', 'compose.yml', 'compose.yaml'] as $file) {
            if (is_file($root.'/'.$file)) {
                $deploy[] = str_contains((string) file_get_contents($root.'/'.$file), 'laravel/sail') ? 'Laravel Sail' : 'Docker Compose';
                break;
            }
        }

        return [
            'git' => [
                'repository' => $git,
                'branch' => $git ? $this->git->currentBranch() : null,
                'commit' => $parts[0] ?? null,
                'subject' => $parts[1] ?? null,
                'committed_at' => $parts[2] ?? null,
                'remote' => $git ? $this->remote() : null,
            ],
            'ci' => $ci,
            'deploy' => array_values(array_unique($deploy)),
        ];
    }

    protected function remote(): ?string
    {
        $url = $this->git->originUrl();

        return is_string($url) && $url !== '' ? ComposerFiles::cleanUrl($url) : null;
    }

    /**
     * What deserves a word on top: a version past its end, one whose end is
     * near, a Laravel that does not support the PHP it runs on.
     *
     * @param  array<string, mixed>  $facts
     * @return list<array{level: string, area: string, message: string}>
     */
    protected function alerts(array $facts): array
    {
        $alerts = [];

        foreach ([
            'laravel' => $facts['laravel']['support'] ?? null,
            'php' => $facts['php']['support'] ?? null,
            'database' => $facts['database']['support'] ?? null,
            'node' => $facts['frontend']['node_support'] ?? null,
        ] as $area => $support) {
            if (! is_array($support) || $support['cycle'] === null) {
                continue;
            }

            $name = $support['label'].' '.$support['cycle'];

            if ($support['state'] === 'eol') {
                $alerts[] = ['level' => 'critical', 'area' => $area, 'message' => $name.' is past its end of life ('.$support['security_until'].'): no security fixes. Upgrade to '.$support['label'].' '.$support['latest'].'.'];
            } elseif ($support['ending']) {
                $alerts[] = ['level' => 'warning', 'area' => $area, 'message' => $name.' receives security fixes until '.$support['security_until'].' ('.$support['days_left'].' days). Plan the upgrade.'];
            } elseif ($support['state'] === 'security') {
                $alerts[] = ['level' => 'info', 'area' => $area, 'message' => $name.' receives security fixes only, until '.$support['security_until'].'.'];
            }
        }

        if (($facts['laravel']['php_supported'] ?? null) === false) {
            $range = $facts['laravel']['php_range'];
            $alerts[] = ['level' => 'warning', 'area' => 'php', 'message' => 'Laravel '.$facts['laravel']['major'].' supports PHP '.$range[0].' to '.$range[1].'; this runs PHP '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'];
        }

        foreach ($facts['packages'] ?? [] as $package) {
            if ($package['abandoned'] !== false) {
                $alerts[] = ['level' => 'warning', 'area' => 'packages', 'message' => $package['name'].' is abandoned'.(is_string($package['abandoned']) ? ': use '.$package['abandoned'].' instead' : '').'.'];
            }
        }

        if (($facts['database']['reachable'] ?? null) === false) {
            $alerts[] = ['level' => 'info', 'area' => 'database', 'message' => 'The database did not answer, so its version is unknown.'];
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
