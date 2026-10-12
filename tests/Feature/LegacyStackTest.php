<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Larapilot\Services\BoostPackageService;
use Larapilot\Services\CodeQualityService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\CustomSkillService;
use Larapilot\Services\FileManagerService;
use Larapilot\Services\GitService;
use Larapilot\Services\PackagedSkillService;

/*
 * An existing app on Laravel 10, PHP 8.1, Boost 1, and Sail: the gate on a
 * codebase never formatted, the dependencies install writes, the skills
 * Boost 1 does not publish, the paths of a container, and the files the
 * dashboard may not replace.
 */

function legacyRepository(): string
{
    $root = sys_get_temp_dir().'/larapilot-legacy-'.bin2hex(random_bytes(4));
    mkdir($root, 0755, true);

    foreach ([
        ['init', '--quiet', '--initial-branch=main'],
        ['config', 'user.email', 'test@example.com'],
        ['config', 'user.name', 'Test'],
    ] as $args) {
        legacyGit($root, ...$args);
    }

    file_put_contents($root.'/Old.php', "<?php\n");
    legacyGit($root, 'add', '.');
    legacyGit($root, 'commit', '--quiet', '-m', 'chore: bootstrap');

    return $root;
}

function legacyGit(string $root, string ...$args): void
{
    $process = proc_open(array_merge(['git', '-C', $root], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    proc_close($process);
}

function withComposerJson(string $content, callable $test): void
{
    $path = base_path('composer.json');
    $before = is_file($path) ? (string) file_get_contents($path) : null;
    file_put_contents($path, $content);

    try {
        $test($path);
    } finally {
        $before === null ? @unlink($path) : file_put_contents($path, $before);
    }
}

function withBoostJson(array $boost, callable $test): void
{
    $path = base_path('boost.json');
    $before = is_file($path) ? (string) file_get_contents($path) : null;
    file_put_contents($path, json_encode($boost, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    try {
        $test($path);
    } finally {
        $before === null ? @unlink($path) : file_put_contents($path, $before);
    }
}

function boostOne(): PackagedSkillService
{
    $service = new class(app(ConfigService::class)) extends PackagedSkillService
    {
        public function boostPublishesSkills(): bool
        {
            return false;
        }
    };

    app()->instance(PackagedSkillService::class, $service);

    return $service;
}

function removeTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }

    foreach (is_dir($path) ? (scandir($path) ?: []) : [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeTree($path.'/'.$entry);
        }
    }

    @rmdir($path);
}

it('reads git without a shell, so a format with % survives', function (): void {
    $root = legacyRepository();

    try {
        $git = app(GitService::class)->at($root);

        expect($git->isRepository())->toBeTrue()
            ->and($git->read('log', '-1', '--format=%s%x1f%H'))->toStartWith("chore: bootstrap\x1f");
    } finally {
        removeTree($root);
    }
});

it('lists what the branch changed against its base, after the commit too', function (): void {
    $root = legacyRepository();

    try {
        legacyGit($root, 'checkout', '--quiet', '-b', 'feature/US-001');
        file_put_contents($root.'/Committed.php', "<?php\n");
        legacyGit($root, 'add', 'Committed.php');
        legacyGit($root, 'commit', '--quiet', '-m', 'feat: committed');
        file_put_contents($root.'/Old.php', "<?php\n// edited\n");
        file_put_contents($root.'/With space é.php', "<?php\n");
        file_put_contents($root.'/notes.txt', "untracked\n");

        $files = app(GitService::class)->at($root)->changedFiles();

        expect($files)->toEqualCanonicalizing(['Committed.php', 'Old.php', 'With space é.php', 'notes.txt']);

        // On the base branch itself, with no upstream: the working tree alone.
        legacyGit($root, 'checkout', '--quiet', 'main');

        expect(app(GitService::class)->at($root)->changedFiles())->toEqualCanonicalizing(['Old.php', 'With space é.php', 'notes.txt']);
    } finally {
        removeTree($root);
    }
});

it('keeps the quality scope as a setting', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(app(ConfigService::class)->qualityScope())->toBe('ALL');

    $this->artisan('larapilot:settings-set', ['--quality-scope' => 'changed'])->assertSuccessful();

    expect(app(ConfigService::class)->qualityScope())->toBe('CHANGED')
        ->and(app(ConfigService::class)->settingsValid())->toBeTrue();

    $this->artisan('larapilot:settings-set', ['--quality-scope' => 'some'])->assertExitCode(2);
});

it('includes the baseline in the PHPStan config once, keeping the rest', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $quality = app(CodeQualityService::class);
    $include = (new ReflectionMethod($quality, 'includeBaseline'));
    $config = base_path('phpstan.neon.dist');

    expect($quality->baselineIncluded())->toBeFalse();

    $include->invoke($quality, $config);
    $include->invoke($quality, $config);
    file_put_contents(base_path(CodeQualityService::BASELINE_FILE), "parameters:\n    ignoreErrors: []\n");

    try {
        $neon = (string) file_get_contents($config);

        expect(substr_count($neon, '- phpstan-baseline.neon'))->toBe(1)
            ->and($neon)->toContain("includes:\n    - phpstan-baseline.neon\n    - vendor/larastan/larastan/extension.neon")
            ->and($neon)->toContain('level: 5')
            ->and($quality->baselineIncluded())->toBeTrue()
            ->and($quality->status()['baseline'])->toBeTrue();
    } finally {
        @unlink(base_path(CodeQualityService::BASELINE_FILE));
    }
});

it('declares only the missing gate packages without reformatting composer.json', function (): void {
    $manifest = <<<'JSON'
{
  "name": "acme/legacy",
  "description": "Gestionale per l'università",
  "keywords": ["laravel", "framework"],
  "require": {
    "php": "^8.1",
    "laravel/framework": "^10.10"
  },
  "require-dev": {
    "laravel/pint": "^1.13"
  }
}

JSON;

    withComposerJson($manifest, function (string $path): void {
        $result = app(CodeQualityService::class)->install(false, false);
        $written = (string) file_get_contents($path);
        $decoded = json_decode($written, true);

        expect($result['composer']['require_dev'])->toBe([CodeQualityService::LARASTAN_PACKAGE])
            ->and($result['packages']['installed'])->toBeFalse()
            ->and($decoded['require-dev'])->toBe([
                'laravel/pint' => '^1.13',
                'larastan/larastan' => '^2.9|^3.0',
            ])
            ->and(array_keys($decoded))->toBe(['name', 'description', 'keywords', 'require', 'require-dev', 'scripts'])
            ->and($written)->toContain("\n  \"name\": \"acme/legacy\"")
            ->and($written)->toContain("l'università")
            ->and($written)->not->toContain('\\u00e0')
            ->and($decoded['scripts']['lint'])->toBe('pint');
    });
});

it('counts a gate package another package brought in', function (): void {
    withComposerJson("{\n    \"name\": \"acme/legacy\"\n}\n", function (): void {
        // laravel/pint is installed here as a dependency of the package itself.
        expect(app(CodeQualityService::class)->missingPackages())->toBe([CodeQualityService::LARASTAN_PACKAGE])
            ->and(app(CodeQualityService::class)->status()['composer_require_dev'][CodeQualityService::PINT_PACKAGE])->toBeTrue();
    });
});

it('leaves composer.json alone on update', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withComposerJson("{\n  \"name\": \"acme/legacy\"\n}\n", function (string $path): void {
        $this->artisan('larapilot:update', ['--skip-boost' => true])->assertSuccessful();

        expect((string) file_get_contents($path))->toBe("{\n  \"name\": \"acme/legacy\"\n}\n");
    });
});

it('keeps Boost 1 when Boost 2 cannot install', function (): void {
    $boost = new class(app()) extends BoostPackageService
    {
        public function installedVersion(): ?string
        {
            return 'v1.8.13';
        }
    };

    withComposerJson("{\n    \"require-dev\": {\n        \"laravel/boost\": \"^1.8\"\n    }\n}\n", function () use ($boost): void {
        expect($boost->boostTwoBlockedBy())->toContain('^1.8');
    });

    withComposerJson("{\n    \"require-dev\": {\n        \"laravel/boost\": \"^1.8|^2.0\"\n    }\n}\n", function () use ($boost): void {
        expect($boost->boostTwoBlockedBy())->toBeNull();
    });

    $current = new class(app()) extends BoostPackageService
    {
        public function installedVersion(): ?string
        {
            return 'v2.10.2';
        }
    };

    expect($current->boostTwoBlockedBy())->toBeNull();
});

it('publishes the packaged skills itself when Boost publishes none', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $skills = boostOne();

    withBoostJson(['agents' => ['claude_code'], 'guidelines' => ['filament/filament']], function () use ($skills): void {
        mkdir(base_path('.claude/skills/larapilot-retired'), 0755, true);
        file_put_contents(base_path('.claude/skills/larapilot-retired/SKILL.md'), 'old');

        try {
            $first = $skills->publish();

            expect($first['folders'])->toBe(['.claude/skills'])
                ->and($first['written'])->toHaveCount(count($skills->names()))
                ->and($first['removed'])->toBe(['.claude/skills/larapilot-retired'])
                ->and(file_get_contents(base_path('.claude/skills/larapilot-implement/SKILL.md')))
                ->toBe(file_get_contents($skills->directory().'/larapilot-implement/SKILL.md'))
                ->and($skills->check()['ok'])->toBeTrue()
                ->and($skills->publish()['written'])->toBe([]);

            file_put_contents(base_path('.claude/skills/larapilot-plan/SKILL.md'), 'stale');
            removeTree(base_path('.claude/skills/larapilot-spec'));

            $check = $skills->check();

            expect($check['publisher'])->toBe('larapilot')
                ->and($check['differs'])->toBe(['.claude/skills/larapilot-plan'])
                ->and($check['missing'])->toBe(['.claude/skills/larapilot-spec']);

            Artisan::call('larapilot:doctor');
            $doctor = json_decode(Artisan::output(), true);

            expect($doctor['data']['checks']['skills'])->toBeFalse()
                ->and($doctor['data']['healthy'])->toBeFalse();

            // Boost 1 reads third-party guidelines only from its list.
            expect($skills->listInBoostJson())->toBe('guidelines')
                ->and(json_decode((string) file_get_contents(base_path('boost.json')), true)['guidelines'])
                ->toBe(['filament/filament', 'andreapollastri/larapilot'])
                ->and($skills->listInBoostJson())->toBeNull();
        } finally {
            removeTree(base_path('.claude'));
        }
    });
});

it('adds the package to the Boost 2 package list only when there is one', function (): void {
    $skills = app(PackagedSkillService::class);

    withBoostJson(['agents' => ['claude_code'], 'packages' => ['livewire/flux']], function () use ($skills): void {
        expect($skills->listInBoostJson())->toBe('packages')
            ->and(json_decode((string) file_get_contents(base_path('boost.json')), true)['packages'])
            ->toBe(['livewire/flux', 'andreapollastri/larapilot']);
    });

    withBoostJson(['agents' => ['claude_code']], function () use ($skills): void {
        expect($skills->listInBoostJson())->toBeNull();
    });
});

it('runs Artisan through Sail when Boost was set up for it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withBoostJson(['agents' => ['claude_code'], 'sail' => true], function (): void {
        $config = new ConfigService;

        expect($config->artisanCommand())->toBe('vendor/bin/sail artisan')
            ->and($config->agentPathsRelative())->toBeTrue();

        $guideline = Blade::render((string) file_get_contents(dirname(__DIR__, 2).'/resources/boost/guidelines/core.blade.php'));

        expect($guideline)->toContain('**Artisan runs as `vendor/bin/sail artisan` in this project**');
    });

    $config = new ConfigService;

    expect($config->artisanCommand())->toBe('php artisan')
        ->and($config->agentPathsRelative())->toBeFalse()
        ->and(Blade::render((string) file_get_contents(dirname(__DIR__, 2).'/resources/boost/guidelines/core.blade.php')))
        ->not->toContain('Artisan runs as');

    config()->set('larapilot.artisan', 'docker compose exec app php artisan');

    expect((new ConfigService)->artisanCommand())->toBe('docker compose exec app php artisan');
});

it('gives the envelopes paths relative to the root when Artisan runs in a container', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    config()->set('larapilot.agent_paths', 'relative');

    Artisan::call('larapilot:context', ['skill' => 'implement']);
    $envelope = json_decode(Artisan::output(), true);

    expect($envelope['data']['project_root'])->toBe('.')
        ->and($envelope['data']['artisan'])->toBe('php artisan')
        ->and($envelope['data']['runtime']['dir'])->toStartWith('.larapilot/')
        ->and(json_encode($envelope))->not->toContain(base_path().'/');

    config()->set('larapilot.agent_paths', 'absolute');

    Artisan::call('larapilot:config-show', ['--only' => 'paths']);
    $absolute = json_decode(Artisan::output(), true);

    expect($absolute['data']['paths']['prd'])->toBe(base_path('.larapilot/docs/PRD.md'));
});

it('keeps temporary skill payloads out of git', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect((string) file_get_contents(base_path('.gitignore')))->toContain("\n/.larapilot/tmp-*\n")
        ->and(app(ConfigService::class)->ignoreTemporaryFiles())->toBeFalse();
});

it('never lets a custom skill take the name of a Larapilot or Boost skill', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    $content = "---\nname: larapilot-implement\ndescription: Hijack.\n---\n\n# Hijack\n";

    expect(fn () => app(CustomSkillService::class)->write('larapilot-implement', $content))
        ->toThrow(InvalidArgumentException::class, 'is a skill of Larapilot');

    expect(app(CustomSkillService::class)->reservedBy('infer-conventions'))->toBe('Laravel Boost');

    $files = app(FileManagerService::class);

    expect(fn () => $files->createFolder('skills', '', 'larapilot-plan'))
        ->toThrow(InvalidArgumentException::class, 'is a skill of Larapilot');

    // A folder put there by hand is not published.
    mkdir(base_path('.larapilot/skills/larapilot-review'), 0755, true);
    file_put_contents(base_path('.larapilot/skills/larapilot-review/SKILL.md'), $content);

    $report = collect(app(CustomSkillService::class)->registerAll())->firstWhere('name', 'larapilot-review');

    expect($report['reserved'])->toBe('Larapilot')
        ->and($report['registered'])->toBe([])
        ->and(base_path('.ai/skills/larapilot-review'))->not->toBeDirectory();
});

it('never overwrites a skill folder Larapilot did not write', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    mkdir(base_path('.claude/skills/deploy'), 0755, true);
    file_put_contents(base_path('.claude/skills/deploy/SKILL.md'), "---\nname: deploy\ndescription: The team's own.\n---\n");

    try {
        $skills = app(CustomSkillService::class);
        $saved = $skills->write('deploy', "---\nname: deploy\ndescription: Uploaded.\n---\n\n# Deploy\n");

        expect($saved['registered'])->toBe(['.ai/skills/deploy'])
            ->and($saved['kept'])->toBe(['.claude/skills/deploy'])
            ->and(file_get_contents(base_path('.claude/skills/deploy/SKILL.md')))->toContain("The team's own.")
            ->and(CustomSkillService::conflicts($skills->registerAll()))->toHaveCount(1);

        // Its own mirror follows an edit of the source.
        file_put_contents(base_path('.larapilot/skills/deploy/SKILL.md'), "---\nname: deploy\ndescription: Edited.\n---\n");
        $skills->registerAll();

        expect(file_get_contents(base_path('.ai/skills/deploy/SKILL.md')))->toContain('Edited.');

        // --force is the developer saying the name is theirs.
        $forced = $skills->write('deploy', "---\nname: deploy\ndescription: Forced.\n---\n", true);

        expect($forced['kept'])->toBe([])
            ->and(file_get_contents(base_path('.claude/skills/deploy/SKILL.md')))->toContain('Forced.');
    } finally {
        removeTree(base_path('.claude'));
    }
});

it('refuses a write on the open API that another site could send', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    enableComments();
    addSpec(['status' => 'PLANNED']);

    $this->post('/larapilot/api/specs/US-001/comments', ['author' => 'Page', 'message' => 'Form post.'])
        ->assertForbidden();

    $this->postJson('/larapilot/api/specs/US-001/comments', ['author' => 'Page', 'message' => 'Cross-site.'], ['Sec-Fetch-Site' => 'cross-site'])
        ->assertForbidden();

    $this->postJson('/larapilot/api/specs/US-001/comments', ['author' => 'Page', 'message' => 'Elsewhere.'], ['Origin' => 'https://evil.example'])
        ->assertForbidden();

    $this->postJson('/larapilot/api/specs/US-001/comments', ['author' => 'CLI', 'message' => 'Same origin.'], ['Origin' => 'http://localhost', 'Sec-Fetch-Site' => 'same-origin'])
        ->assertCreated();
});
