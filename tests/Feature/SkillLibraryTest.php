<?php

declare(strict_types=1);

use Larapilot\Services\AgentGuidelineService;
use Larapilot\Services\SkillLibraryService;

function customSkill(string $name, string $content): void
{
    test()->artisan('larapilot:custom-skill-add', ['--name' => $name, '--content' => $content])->assertSuccessful();
}

it('lists the skills of the project and the ones that ship with the package', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    customSkill('deploy-checklist', <<<'MD'
---
name: deploy-checklist
description: Run the team deploy checklist before a production ship.
---

# Deploy checklist

Walk Jack through the pre-ship gate.
MD);

    $library = app(SkillLibraryService::class)->all();

    expect(array_column($library['custom'], 'name'))->toBe(['deploy-checklist'])
        ->and($library['custom'][0]['origin'])->toBe('custom')
        ->and($library['custom'][0]['registered'])->toBeTrue()
        ->and($library['custom'][0]['relative_path'])->toBe('.larapilot/skills/deploy-checklist/SKILL.md')
        ->and(array_column($library['packaged'], 'name'))->toContain('larapilot-inception', 'larapilot-triage', 'larapilot-aikido')
        ->and(count($library['packaged']))->toBe(count(glob(dirname(__DIR__, 2).'/resources/boost/skills/*/SKILL.md') ?: []));

    foreach ($library['packaged'] as $skill) {
        expect($skill['origin'])->toBe('packaged')
            ->and($skill['registered'])->toBeNull()
            ->and($skill['description'])->not->toBeEmpty()
            ->and($skill['trigger'])->toBe('/'.$skill['name']);
    }

    $this->get('/larapilot/skills')
        ->assertOk()
        ->assertSee('Click a skill to read it.', false)
        ->assertSee('href="'.url('/larapilot/skills/deploy-checklist').'"', false)
        ->assertSee('Run the team deploy checklist before a production ship.', false)
        ->assertSee('Registered', false)
        ->assertSee('Packaged with Larapilot', false)
        ->assertSee('href="'.url('/larapilot/skills/larapilot-triage').'"', false)
        ->assertSee('/larapilot-aikido', false)
        ->assertSee('id="skills-filter"', false);
});

it('says so when the project has no skill of its own', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/skills')
        ->assertOk()
        ->assertSee('No custom skills yet.', false)
        ->assertSee('Packaged with Larapilot', false);
});

it('reads a skill as a document, not as a file', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    customSkill('deploy-checklist', <<<'MD'
---
name: deploy-checklist
description: Run the team deploy checklist before a production ship.
---

# Deploy checklist

Walk **Jack** through the pre-ship gate.

## Before the deploy

| Check | Owner |
| --- | --- |
| Migrations reviewed | Andrew |

- [x] Backups verified
- [ ] Queue drained

```bash
php artisan migrate --pretend
```

### Rollback

Keep the last release.

<script>alert('never run')</script>
MD);
    file_put_contents(base_path('.larapilot/skills/deploy-checklist/checklist.md'), '# Checklist');

    $html = $this->get('/larapilot/skills/deploy-checklist')
        ->assertOk()
        ->assertSee('All skills', false)
        ->assertSee('/deploy-checklist', false)
        ->assertSee('Run the team deploy checklist before a production ship.', false)
        ->assertSee('Written for this project', false)
        ->assertSee('.larapilot/skills/deploy-checklist/SKILL.md', false)
        ->assertSee('Registered', false)
        // the sections, to jump to
        ->assertSee('In this skill', false)
        ->assertSee('<a href="#before-the-deploy">Before the deploy</a>', false)
        ->assertSee('<a href="#rollback">Rollback</a>', false)
        // the text, rendered
        ->assertSee('<h2 id="before-the-deploy">Before the deploy</h2>', false)
        ->assertSee('<strong>Jack</strong>', false)
        ->assertSee('<th>Check</th>', false)
        ->assertSee('<td>Migrations reviewed</td>', false)
        ->assertSee('php artisan migrate --pretend', false)
        ->assertSee('type="checkbox"', false)
        // what sits beside it
        ->assertSee('Files beside it', false)
        ->assertSee('checklist.md', false)
        ->assertSee('Download SKILL.md', false)
        ->getContent();

    // The front matter is facts on the page, never text of the document.
    expect($html)->not->toContain('name: deploy-checklist')
        // the title is said once
        ->and(substr_count($html, '<h1'))->toBe(0)
        // markup in a skill is text, whatever it says
        ->and($html)->not->toContain("<script>alert('never run')</script>");

    $download = $this->get('/larapilot/skills/deploy-checklist/SKILL.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($download->headers->get('Content-Disposition'))->toBe('attachment; filename="deploy-checklist-SKILL.md"')
        ->and($download->getContent())->toBe((string) file_get_contents(base_path('.larapilot/skills/deploy-checklist/SKILL.md')));
});

it('reads a skill that ships with the package', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/skills/larapilot-triage')
        ->assertOk()
        ->assertSee('Larapilot — Triage', false)
        ->assertSee('Ships with Larapilot', false)
        ->assertSee('Packaged', false)
        ->assertSee('<a href="#the-promise-test">The promise test</a>', false)
        ->assertSee('<h2 id="the-promise-test">The promise test</h2>', false)
        ->assertSee('Wording is a hint, never the verdict', false)
        ->assertDontSee('Not registered', false)
        ->assertDontSee('Open folder', false);

    expect($this->get('/larapilot/skills/larapilot-triage/SKILL.md')->assertOk()->getContent())
        ->toBe((string) file_get_contents(dirname(__DIR__, 2).'/resources/boost/skills/larapilot-triage/SKILL.md'));

    foreach (glob(dirname(__DIR__, 2).'/resources/boost/skills/*', GLOB_ONLYDIR) ?: [] as $folder) {
        $this->get('/larapilot/skills/'.basename($folder))->assertOk();
    }
});

it('finds a skill by looking it up, never by building a path', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    file_put_contents(base_path('.larapilot/secret.md'), '# Not a skill');

    foreach ([
        '/larapilot/skills/nope',
        '/larapilot/skills/..%2Fsecret',
        '/larapilot/skills/..',
        '/larapilot/skills/.larapilot',
        '/larapilot/skills/nope/SKILL.md',
        '/larapilot/skills/..%2F..%2Fcomposer/SKILL.md',
    ] as $url) {
        expect($this->get($url)->getStatusCode())->toBe(404, $url);
    }

    expect(app(SkillLibraryService::class)->find('../secret'))->toBeNull()
        ->and(app(SkillLibraryService::class)->find(''))->toBeNull()
        ->and(app(SkillLibraryService::class)->find('LARAPILOT-TRIAGE')['name'])->toBe('larapilot-triage');

    config()->set('larapilot.dashboard_route.enabled', false);

    $this->get('/larapilot/skills/larapilot-triage')->assertNotFound();
    $this->get('/larapilot/skills/larapilot-triage/SKILL.md')->assertNotFound();
});

/**
 * A file planted in the sandbox, with the folders it needs.
 */
function plant(string $relative, string $content): string
{
    $path = base_path($relative);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, $content);

    return $path;
}

function skillText(string $name, string $description, string $body = 'Do the work.'): string
{
    return "---\nname: {$name}\ndescription: \"{$description}\"\nlicense: MIT\nmetadata:\n  author: acme\n---\n\n# ".ucfirst(str_replace('-', ' ', $name))."\n\n{$body}\n";
}

/**
 * A project with packages installed in a folder of their own, so the real
 * `vendor` is never written to.
 *
 * @param  array<string, string>  $require
 */
function projectWithPackages(array $require = []): void
{
    plant('composer.json', (string) json_encode([
        'name' => 'acme/app',
        'config' => ['vendor-dir' => 'test-vendor'],
        'require' => $require + ['laravel/boost' => '^2.0'],
    ], JSON_UNESCAPED_SLASHES));
}

afterEach(function (): void {
    foreach (['test-vendor', 'node_modules', '.claude', '.cursor', '.agents', '.github'] as $folder) {
        if (is_link(base_path($folder))) {
            unlink(base_path($folder));
        } elseif (is_dir(base_path($folder))) {
            $this->deleteDirectory(base_path($folder));
        }
    }

    foreach (['CLAUDE.md', 'AGENTS.md', 'boost.json', 'composer.json', 'package.json'] as $file) {
        if (is_file(base_path($file))) {
            unlink(base_path($file));
        }
    }
});

it('reads the skills that packages and Boost bring, and says which agent has them', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    projectWithPackages(['acme/toolkit' => '^1.0']);

    $reports = skillText('toolkit-reports', 'Build a report with the toolkit.', "## Columns\n\nOne for each measure.");
    $practices = skillText('laravel-best-practices', 'Apply whenever Laravel code is written.');
    $tailwind3 = skillText('tailwindcss-development', 'Tailwind classes.', "Use `tailwind.config.js`.\n\nThe third version.");
    $tailwind4 = skillText('tailwindcss-development', 'Tailwind classes.', "Use `@theme` in CSS.\n\nThe fourth version.");

    plant('test-vendor/acme/toolkit/resources/boost/skills/toolkit-reports/SKILL.md', $reports);
    plant('test-vendor/acme/toolkit/resources/boost/skills/toolkit-reports/references/columns.md', '# Columns');
    plant('test-vendor/other/helper/resources/boost/skills/helper-tips/SKILL.md', skillText('helper-tips', 'Tips of a package nobody asked for.'));
    plant('test-vendor/laravel/boost/.ai/laravel/skill/laravel-best-practices/SKILL.md', $practices);
    plant('test-vendor/laravel/boost/.ai/tailwindcss/3/skill/tailwindcss-development/SKILL.md', $tailwind3);
    plant('test-vendor/laravel/boost/.ai/tailwindcss/4/skill/tailwindcss-development/SKILL.md', $tailwind4);
    plant('test-vendor/laravel/boost/.ai/pennant/skill/pennant-development/SKILL.md', skillText('pennant-development', 'Feature flags.'));
    // a package of JavaScript brings skills too
    plant('package.json', (string) json_encode(['devDependencies' => ['@acme/charts' => '^2.0']]));
    plant('node_modules/@acme/charts/resources/boost/skills/charts-drawing/SKILL.md', skillText('charts-drawing', 'Draw a chart.'));

    // what Boost published to Claude Code, and a skill somebody dropped there
    plant('.claude/skills/toolkit-reports/SKILL.md', $reports);
    plant('.claude/skills/laravel-best-practices/SKILL.md', $practices."\nAn older line.\n");
    plant('.claude/skills/tailwindcss-development/SKILL.md', $tailwind4);
    plant('.claude/skills/by-hand/SKILL.md', skillText('by-hand', 'Somebody wrote this in the folder of the agent.'));
    plant('boost.json', (string) json_encode(['agents' => ['claude_code'], 'skills' => ['toolkit-reports', 'laravel-best-practices', 'tailwindcss-development']]));

    $library = app(SkillLibraryService::class)->all();
    $packages = collect($library['packages'])->keyBy('name');
    $boost = collect($library['boost']);

    expect($packages->keys()->sort()->values()->all())->toBe(['charts-drawing', 'helper-tips', 'toolkit-reports'])
        ->and($packages['toolkit-reports']['source'])->toBe('acme/toolkit')
        ->and($packages['toolkit-reports']['id'])->toBe('package.acme-toolkit')
        ->and($packages['toolkit-reports']['direct'])->toBeTrue()
        ->and($packages['toolkit-reports']['tracked'])->toBeTrue()
        ->and($packages['toolkit-reports']['published'])->toBeTrue()
        ->and($packages['toolkit-reports']['relative_path'])->toBe('test-vendor/acme/toolkit/resources/boost/skills/toolkit-reports/SKILL.md')
        ->and($packages['toolkit-reports']['installed'][0]['agent'])->toBe('Claude Code')
        ->and($packages['toolkit-reports']['installed'][0]['state'])->toBe('same')
        ->and($packages['toolkit-reports']['author'])->toBe('acme')
        // a package that came with another one: Boost leaves it out
        ->and($packages['helper-tips']['direct'])->toBeFalse()
        ->and($packages['helper-tips']['published'])->toBeFalse()
        ->and($packages['charts-drawing']['source'])->toBe('@acme/charts')
        ->and($packages['charts-drawing']['manager'])->toBe('npm')
        ->and($packages['charts-drawing']['direct'])->toBeTrue()
        // Larapilot is not one of the other packages
        ->and(array_column($library['packages'], 'source'))->not->toContain('andreapollastri/larapilot', 'laravel/boost');

    // Boost: the published ones first, and one version of the two
    expect($boost->pluck('published', 'id')->all())->toBe([
        'boost.laravel' => true,
        'boost.tailwindcss-4' => true,
        'boost.pennant' => false,
        'boost.tailwindcss-3' => false,
    ])
        ->and($boost->firstWhere('id', 'boost.laravel')['installed'][0]['state'])->toBe('differs')
        ->and($boost->firstWhere('id', 'boost.tailwindcss-4')['version'])->toBe('4')
        ->and($boost->firstWhere('id', 'boost.tailwindcss-3')['installed'])->toBe([]);

    expect(array_column($library['agent'], 'name'))->toBe(['by-hand'])
        ->and($library['agent'][0]['relative_path'])->toBe('.claude/skills/by-hand/SKILL.md')
        ->and($library['agents'])->toBe([['folder' => '.claude/skills', 'agent' => 'Claude Code', 'skills' => 4]])
        ->and($library['boost_state'])->toBe([
            'installed' => true,
            'configured' => true,
            'tracked' => ['toolkit-reports', 'laravel-best-practices', 'tailwindcss-development'],
            'agents' => ['claude_code'],
        ]);

    $this->get('/larapilot/skills')
        ->assertOk()
        ->assertSee('Who reads them here', false)
        ->assertSee('<b>Claude Code</b> <code>.claude/skills/</code> <small>4 skills</small>', false)
        ->assertSee('From other packages', false)
        ->assertSee('3 skills from 3 packages', false)
        ->assertSee('href="'.url('/larapilot/skills/toolkit-reports').'?from=package.acme-toolkit"', false)
        ->assertSee('From Laravel Boost', false)
        ->assertSee('href="'.url('/larapilot/skills/tailwindcss-development').'?from=boost.tailwindcss-4"', false)
        ->assertSee('2 more skills Boost ships and no agent of this project has', false)
        ->assertSee('Only in the folder of an agent', false)
        ->assertSee('/by-hand', false)
        ->assertSee('not the same text', false)
        ->assertSee('No agent has it', false);

    $this->get('/larapilot/skills/toolkit-reports?from=package.acme-toolkit')
        ->assertOk()
        ->assertSee('Brought by acme/toolkit', false)
        ->assertSee('By acme', false)
        ->assertSee('MIT license', false)
        ->assertSee('<h2 id="columns">Columns</h2>', false)
        ->assertSee('Where the agents find it', false)
        ->assertSee('.claude/skills/toolkit-reports/SKILL.md', false)
        ->assertSee('Same text as the source', false)
        ->assertSee('references/columns.md', false)
        ->assertSee('href="'.url('/larapilot/skills/toolkit-reports/SKILL.md').'?from=package.acme-toolkit"', false)
        ->assertDontSee('Open folder', false);

    // the name alone is enough when one skill carries it
    $this->get('/larapilot/skills/toolkit-reports')->assertOk()->assertSee('Brought by acme/toolkit', false);

    $this->get('/larapilot/skills/helper-tips?from=package.other-helper')
        ->assertOk()
        ->assertSee('came with another package', false)
        ->assertSee('Not there', false);

    $this->get('/larapilot/skills/laravel-best-practices?from=boost.laravel')
        ->assertOk()
        ->assertSee('Built into Laravel Boost, for laravel', false)
        ->assertSee('Not the same text as the source', false)
        ->assertSee('php artisan boost:update', false);

    // two versions under one name: the published one is read, the other is a click away
    $this->get('/larapilot/skills/tailwindcss-development')
        ->assertOk()
        ->assertSee('The fourth version.', false)
        ->assertDontSee('The third version.', false)
        ->assertSee('Other skills of this name', false)
        ->assertSee('From Laravel Boost, version 3', false)
        ->assertSee('href="'.url('/larapilot/skills/tailwindcss-development').'?from=boost.tailwindcss-3"', false);

    $this->get('/larapilot/skills/tailwindcss-development?from=boost.tailwindcss-3')
        ->assertOk()
        ->assertSee('The third version.', false);

    $this->get('/larapilot/skills/by-hand?from=agent.claude-skills')
        ->assertOk()
        ->assertSee('Only in the folder of an agent', false)
        ->assertSee('No package and no folder of the project accounts for this skill.', false);

    expect($this->get('/larapilot/skills/toolkit-reports/SKILL.md?from=package.acme-toolkit')->assertOk()->getContent())->toBe($reports);

    // where a skill comes from is looked up like its name
    foreach ([
        '/larapilot/skills/toolkit-reports?from=package.nope',
        '/larapilot/skills/toolkit-reports?from=boost.laravel',
        '/larapilot/skills/toolkit-reports?from=..%2F..%2Fetc',
        '/larapilot/skills/toolkit-reports?from=Package.Acme-Toolkit',
        '/larapilot/skills/toolkit-reports/SKILL.md?from=package.nope',
    ] as $url) {
        expect($this->get($url)->getStatusCode())->toBe(404, $url);
    }
});

it('reads a template as Boost published it, and never runs it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    projectWithPackages();

    $template = "---\nname: livewire-development\ndescription: \"Livewire components.\"\n---\n@php\nfile_put_contents(base_path('ran.txt'), 'ran');\n@endphp\n# Livewire\n\nVersion {{ \$assist->version() }} of Livewire.\n";

    plant('test-vendor/laravel/boost/.ai/livewire/4/skill/livewire-development/SKILL.blade.php', $template);

    // nobody has it: the template is shown as text
    $this->get('/larapilot/skills/livewire-development?from=boost.livewire-4')
        ->assertOk()
        ->assertSee('This skill is a template Boost fills in when it publishes it.', false)
        ->assertSee('$assist-&gt;version()', false);

    plant('.claude/skills/livewire-development/SKILL.md', "---\nname: livewire-development\ndescription: \"Livewire components.\"\n---\n# Livewire\n\nVersion 4 of Livewire.\n");

    $skill = app(SkillLibraryService::class)->find('livewire-development');

    expect($skill['template'])->toBeTrue()
        ->and($skill['installed'][0]['state'])->toBe('made')
        ->and($skill['shown_from'])->toBe('.claude/skills/livewire-development/SKILL.md')
        ->and($skill['html'])->toContain('Version 4 of Livewire.')
        // the download is the source, as it is on disk
        ->and($skill['content'])->toBe($template);

    $this->get('/larapilot/skills/livewire-development')
        ->assertOk()
        ->assertSee('It is shown as Boost published it, from <code>.claude/skills/livewire-development/SKILL.md</code>.', false)
        ->assertSee('Version 4 of Livewire.', false)
        ->assertSee('Made by Boost from the template', false);

    expect(is_file(base_path('ran.txt')))->toBeFalse();
});

it('tells a copy from a skill of its own in .ai', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    projectWithPackages(['acme/toolkit' => '^1.0']);

    customSkill('deploy-checklist', skillText('deploy-checklist', 'Run the team deploy checklist.'));
    plant('test-vendor/acme/toolkit/resources/boost/skills/toolkit-reports/SKILL.md', skillText('toolkit-reports', 'Build a report with the toolkit.'));
    // the team wrote one for Boost, and keeps its own version of a package skill
    plant('.ai/skills/team-notes/SKILL.md', skillText('team-notes', 'How the team writes release notes.'));
    plant('.ai/skills/toolkit-reports/SKILL.md', skillText('toolkit-reports', 'Build a report with the toolkit.', 'The way this team does it.'));
    plant('.ai/pest/skill/pest-house-rules/SKILL.md', skillText('pest-house-rules', 'How the team writes tests.'));

    $library = app(SkillLibraryService::class)->all();

    // the copy Larapilot registered is not a second skill
    expect(array_column($library['custom'], 'name'))->toBe(['deploy-checklist'])
        ->and(array_column($library['project'], 'name'))->toBe(['pest-house-rules', 'team-notes'])
        ->and($library['project'][0]['id'])->toBe('project.pest')
        ->and($library['project'][1]['relative_path'])->toBe('.ai/skills/team-notes/SKILL.md')
        ->and($library['packages'][0]['override'])->toBe('.ai/skills/toolkit-reports/SKILL.md')
        ->and($library['custom'][0]['override'])->toBeNull();

    $this->get('/larapilot/skills')
        ->assertOk()
        ->assertSee('Written for Boost in <code>.ai/</code>', false)
        ->assertSee('href="'.url('/larapilot/skills/team-notes').'?from=project.ai-skills"', false);

    $this->get('/larapilot/skills/toolkit-reports')
        ->assertOk()
        ->assertSee('The project keeps its own version of this skill in <code>.ai/skills/toolkit-reports/SKILL.md</code>.', false);

    $this->get('/larapilot/skills/team-notes?from=project.ai-skills')
        ->assertOk()
        ->assertSee('Written for Boost by the team', false);
});

it('does not follow a link that leads out of the project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    projectWithPackages();

    $outside = sys_get_temp_dir().'/larapilot-outside-'.bin2hex(random_bytes(4));
    mkdir($outside.'/leaked', 0755, true);
    file_put_contents($outside.'/leaked/SKILL.md', skillText('leaked', 'Not of this project.'));
    mkdir(base_path('.claude/skills'), 0755, true);
    symlink($outside.'/leaked', base_path('.claude/skills/leaked'));

    try {
        expect(array_column(app(SkillLibraryService::class)->all()['agent'], 'name'))->toBe([])
            ->and(app(SkillLibraryService::class)->find('leaked'))->toBeNull();

        $this->get('/larapilot/skills/leaked')->assertNotFound();
        $this->get('/larapilot/skills')->assertOk()->assertDontSee('Not of this project.', false);
    } finally {
        unlink(base_path('.claude/skills/leaked'));
        unlink($outside.'/leaked/SKILL.md');
        rmdir($outside.'/leaked');
        rmdir($outside);
    }
});

it('reads what the agents are told, one part for each author', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    projectWithPackages(['acme/toolkit' => '^1.0']);
    plant('test-vendor/acme/toolkit/resources/boost/guidelines/core.blade.php', '# Toolkit');

    plant('CLAUDE.md', <<<'MD'
# House rules

Never push on a Friday.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

Follow the conventions of the application.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands.

=== andreapollastri/larapilot/core rules ===

## Larapilot

Run `/larapilot-triage` for any request.

=== acme/toolkit/core rules ===

## Toolkit

<script>alert('never run')</script>

=== .ai/team rules ===

## Team

Write the tests first.

</laravel-boost-guidelines>

## Contacts

Ask Andrea.
MD);
    plant('.cursor/rules/style.mdc', "---\ndescription: Code style of the team\nglobs: app/**/*.php\n---\n\n# Style\n\n## Naming\n\nSay what it is.\n");
    plant('.ai/guidelines/team.md', "## Team\n\nWrite the tests first.\n");

    $files = collect(app(AgentGuidelineService::class)->all())->keyBy('id');

    expect($files->keys()->all())->toBe(['claude-md', 'cursor-rules-style-mdc', 'ai-guidelines-team-md'])
        ->and($files['claude-md']['reader'])->toBe('Claude Code')
        ->and($files['claude-md']['managed'])->toBeTrue()
        ->and($files['claude-md']['origins'])->toBe(['hand' => 2, 'boost' => 2, 'larapilot' => 1, 'package' => 1, 'project' => 1])
        ->and(array_column($files['claude-md']['sections'], 'key'))->toBe([
            'Before the guidelines of Boost',
            'foundation',
            'laravel/core',
            'andreapollastri/larapilot/core',
            'acme/toolkit/core',
            '.ai/team',
            'After the guidelines of Boost',
        ])
        ->and($files['claude-md']['sections'][4]['source'])->toBe('acme/toolkit')
        // the text is not in the list
        ->and($files['claude-md']['sections'][0])->not->toHaveKey('markdown')
        ->and($files['cursor-rules-style-mdc']['managed'])->toBeFalse()
        ->and($files['cursor-rules-style-mdc']['kind'])->toBe('agent')
        ->and($files['ai-guidelines-team-md']['kind'])->toBe('source');

    $this->get('/larapilot/skills')
        ->assertOk()
        ->assertSee('What the agents are told', false)
        ->assertSee('href="'.url('/larapilot/skills/guidelines/claude-md').'"', false)
        ->assertSee('Written by Boost', false)
        ->assertSee('2 By hand', false)
        ->assertSee('1 Larapilot', false);

    $html = $this->get('/larapilot/skills/guidelines/claude-md')
        ->assertOk()
        ->assertSee('Read by Claude Code.', false)
        ->assertSee('a change made by hand inside those parts is lost', false)
        ->assertSee('<b>7 parts</b>', false)
        ->assertSee('In this file', false)
        ->assertSee('<a href="#part-3"><span>laravel/core</span><small>Boost</small></a>', false)
        ->assertSee('Written by Larapilot', false)
        ->assertSee('Written by a package of the project: acme/toolkit', false)
        ->assertSee('Written by the team, in .ai/guidelines/', false)
        ->assertSee('Outside what Boost writes', false)
        ->assertSee('Never push on a Friday.', false)
        ->assertSee('Ask Andrea.', false)
        ->assertSee('<code>php artisan make:</code>', false)
        ->getContent();

    // the markers of Boost are not text, one title on the page, no markup run
    expect($html)->not->toContain('=== laravel/core rules ===')
        ->and($html)->not->toContain('laravel-boost-guidelines&gt;')
        ->and($html)->not->toContain('laravel-boost-guidelines>')
        ->and(substr_count($html, '<h1'))->toBe(0)
        ->and($html)->not->toContain("<script>alert('never run')</script>");

    // a file Boost did not write is read as one document
    $this->get('/larapilot/skills/guidelines/cursor-rules-style-mdc')
        ->assertOk()
        ->assertSee('Read by Cursor.', false)
        ->assertSee('Boost wrote nothing here', false)
        ->assertSee('globs: <code>app/**/*.php</code>', false)
        ->assertSee('<a href="#naming">Naming</a>', false)
        ->assertSee('<h2 id="naming">Naming</h2>', false)
        ->assertDontSee('description: Code style of the team', false);

    foreach ([
        '/larapilot/skills/guidelines/nope',
        '/larapilot/skills/guidelines/composer-json',
        '/larapilot/skills/guidelines/..%2F..%2Fcomposer',
        '/larapilot/skills/guidelines/CLAUDE.md',
    ] as $url) {
        expect($this->get($url)->getStatusCode())->toBe(404, $url);
    }

    config()->set('larapilot.dashboard_route.enabled', false);

    $this->get('/larapilot/skills/guidelines/claude-md')->assertNotFound();
});

it('says so when no agent is set up and nothing is told', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    projectWithPackages();

    $this->get('/larapilot/skills')
        ->assertOk()
        ->assertSee('No agent has a folder of skills in this project yet.', false)
        ->assertSee('No file of this kind in the project', false)
        ->assertDontSee('From other packages', false)
        ->assertDontSee('From Laravel Boost', false)
        ->assertDontSee('No agent has it', false);
});
