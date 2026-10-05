<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DashboardAuthService;
use Larapilot\Services\FileManagerService;

function material(string $relative, string $contents = 'x'): string
{
    $path = base_path('.larapilot/'.$relative);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, $contents);

    return $path;
}

/**
 * Outside the `testing` environment the CSRF check is live, so a write has
 * to carry the session token like the dashboard forms do.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function signed(array $data = []): array
{
    test()->withSession(['_token' => 'file-manager-test']);

    return $data + ['_token' => 'file-manager-test'];
}

it('lists the five material folders with what each one is for', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/files')
        ->assertOk()
        ->assertSee('File manager')
        ->assertSee('.larapilot/brand/', false)
        ->assertSee('Logo, palette, typography, and the brand guide.', false)
        ->assertSee('.larapilot/client-materials/', false)
        ->assertSee('Briefs, analyses, and documents supplied by the client.', false)
        ->assertSee('.larapilot/design-systems/', false)
        ->assertSee('Visual references and tokens the mockups are built on.', false)
        ->assertSee('.larapilot/legacy/', false)
        ->assertSee('Snapshots of the old system to port or migrate.', false)
        ->assertSee('.larapilot/skills/', false)
        ->assertSee('Your custom skills, one folder per slash command.', false)
        // and the project itself, to read
        ->assertSee('/larapilot/files/project', false)
        ->assertSee('The Laravel application itself: code, config, routes, and tests. Folders that start with a dot (.git, .larapilot) are left out.', false)
        ->assertSee('Read only', false)
        ->assertSee('Counted without vendor/, node_modules/', false);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('>File manager</a>', false);
});

it('browses a folder, its tree, and hides housekeeping files', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('brand/logos/dark/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    material('brand/guide.md', "# Brand guide\n\nUse the **fjord** blue.");

    $this->get('/larapilot/files/brand')
        ->assertOk()
        ->assertSee('guide.md', false)
        ->assertSee('logos', false)
        ->assertSee('/larapilot/files/brand/logos/dark', false)
        ->assertDontSee('.gitkeep', false);

    $this->get('/larapilot/files/brand/logos/dark')
        ->assertOk()
        ->assertSee('mark.svg', false)
        ->assertSee('.larapilot/brand/logos/dark/', false);

    $this->get('/larapilot/files/brand/guide.md')
        ->assertOk()
        ->assertSee('<strong>fjord</strong>', false)
        ->assertSee('/larapilot/files/raw/brand/guide.md?download=1', false);

    $this->get('/larapilot/files/brand/logos/dark/mark.svg')
        ->assertOk()
        ->assertSee('<img src="'.url('/larapilot/files/raw/brand/logos/dark/mark.svg').'"', false);
});

it('uploads a folder and rebuilds its tree', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->post('/larapilot/files/client-materials/upload', [
        'path' => '',
        'files' => [
            UploadedFile::fake()->createWithContent('brief.md', '# Brief'),
            UploadedFile::fake()->createWithContent('flow.txt', 'checkout'),
            UploadedFile::fake()->createWithContent('.DS_Store', 'junk'),
            UploadedFile::fake()->createWithContent('HEAD', 'ref: refs/heads/main'),
        ],
        'paths' => [
            'kickoff/brief.md',
            'kickoff/wireframes/checkout/flow.txt',
            'kickoff/.DS_Store',
            'kickoff/.git/HEAD',
        ],
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('stored', [
            'kickoff/brief.md',
            'kickoff/wireframes/checkout/flow.txt',
        ])
        ->assertJsonPath('skipped', []);

    expect(base_path('.larapilot/client-materials/kickoff/brief.md'))->toBeFile()
        ->and(file_get_contents(base_path('.larapilot/client-materials/kickoff/wireframes/checkout/flow.txt')))->toBe('checkout')
        ->and(file_exists(base_path('.larapilot/client-materials/kickoff/.DS_Store')))->toBeFalse()
        ->and(is_dir(base_path('.larapilot/client-materials/kickoff/.git')))->toBeFalse();

    $this->get('/larapilot/files/client-materials/kickoff/wireframes')
        ->assertOk()
        ->assertSee('checkout', false);
});

it('keeps an existing file unless replace is on', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('legacy/schema.sql', 'old');

    $this->post('/larapilot/files/legacy/upload', [
        'files' => [UploadedFile::fake()->createWithContent('schema.sql', 'new')],
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('stored', [])
        ->assertJsonPath('skipped.0.path', 'schema.sql');

    expect(file_get_contents(base_path('.larapilot/legacy/schema.sql')))->toBe('old');

    $this->post('/larapilot/files/legacy/upload', [
        'files' => [UploadedFile::fake()->createWithContent('schema.sql', 'new')],
        'replace' => '1',
    ])->assertRedirect('/larapilot/files/legacy');

    expect(file_get_contents(base_path('.larapilot/legacy/schema.sql')))->toBe('new');
});

it('refuses an upload that would leave the folder', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->post('/larapilot/files/brand/upload', [
        'files' => [UploadedFile::fake()->createWithContent('evil.txt', 'x')],
        'paths' => ['../../evil.txt'],
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('stored', []);

    $this->post('/larapilot/files/brand/upload', [
        'path' => '../',
        'files' => [UploadedFile::fake()->createWithContent('evil.txt', 'x')],
    ], ['Accept' => 'application/json'])->assertStatus(422);

    expect(file_exists(base_path('.larapilot/evil.txt')))->toBeFalse()
        ->and(file_exists(base_path('evil.txt')))->toBeFalse();
});

it('creates, renames, and deletes folders and files', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('brand/old name.png', 'png');

    $this->post('/larapilot/files/brand/folder', ['path' => '', 'name' => 'Logos'])
        ->assertRedirect('/larapilot/files/brand/Logos');

    expect(base_path('.larapilot/brand/Logos/.gitkeep'))->toBeFile();

    $this->post('/larapilot/files/brand/rename', ['path' => 'old name.png', 'name' => 'mark #1.png'])
        ->assertRedirect('/larapilot/files/brand')
        ->assertSessionHas('larapilot_success');

    expect(base_path('.larapilot/brand/mark #1.png'))->toBeFile()
        ->and(file_exists(base_path('.larapilot/brand/old name.png')))->toBeFalse();

    $this->get('/larapilot/files/brand')
        ->assertOk()
        ->assertSee('/larapilot/files/brand/mark%20%231.png', false);

    $this->get('/larapilot/files/brand/mark%20%231.png')->assertOk();

    $this->post('/larapilot/files/brand/rename', ['path' => 'Logos', 'name' => 'Marks'])
        ->assertRedirect('/larapilot/files/brand');

    material('brand/Marks/nested/deep.txt', 'deep');

    $this->post('/larapilot/files/brand/delete', ['path' => 'Marks'])
        ->assertRedirect('/larapilot/files/brand')
        ->assertSessionHas('larapilot_success');

    expect(is_dir(base_path('.larapilot/brand/Marks')))->toBeFalse();

    $this->post('/larapilot/files/brand/delete', ['path' => 'mark #1.png'])
        ->assertRedirect('/larapilot/files/brand');

    expect(file_exists(base_path('.larapilot/brand/mark #1.png')))->toBeFalse()
        ->and(is_dir(base_path('.larapilot/brand')))->toBeTrue();
});

it('rejects names and targets that are not allowed', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('brand/a.txt', 'a');
    material('brand/b.txt', 'b');

    $this->post('/larapilot/files/brand/rename', ['path' => 'a.txt', 'name' => 'b.txt'])
        ->assertSessionHas('larapilot_error');

    $this->post('/larapilot/files/brand/rename', ['path' => 'a.txt', 'name' => '../a.txt'])
        ->assertSessionHas('larapilot_error');

    $this->post('/larapilot/files/brand/rename', ['path' => 'a.txt', 'name' => '.gitkeep'])
        ->assertSessionHas('larapilot_error');

    $this->post('/larapilot/files/brand/rename', ['path' => '', 'name' => 'renamed'])
        ->assertSessionHas('larapilot_error');

    $this->post('/larapilot/files/brand/delete', ['path' => ''])
        ->assertSessionHas('larapilot_error');

    $this->post('/larapilot/files/brand/delete', ['path' => '../docs'])
        ->assertSessionHas('larapilot_error');

    $this->post('/larapilot/files/brand/folder', ['path' => '', 'name' => 'a/b'])
        ->assertSessionHas('larapilot_error');

    expect(file_get_contents(base_path('.larapilot/brand/a.txt')))->toBe('a')
        ->and(file_get_contents(base_path('.larapilot/brand/b.txt')))->toBe('b')
        ->and(is_dir(base_path('.larapilot/brand')))->toBeTrue()
        ->and(is_dir(base_path('.larapilot/docs')))->toBeTrue();
});

it('never reads outside the folder', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    app(ConfigService::class)->updateSettings(['effort' => 'STANDARD']);

    $this->get('/larapilot/files/brand/../config.yaml')->assertNotFound();
    $this->get('/larapilot/files/brand/%2E%2E/config.yaml')->assertNotFound();
    $this->get('/larapilot/files/raw/brand/%2E%2E/config.yaml')->assertNotFound();
    $this->get('/larapilot/files/docs')->assertNotFound();
    $this->get('/larapilot/files/raw/docs/PRD.md')->assertNotFound();
    $this->post('/larapilot/files/docs/delete', ['path' => 'PRD.md'])->assertNotFound();

    $files = app(FileManagerService::class);

    expect($files->browse('brand', '../config.yaml'))->toBeNull()
        ->and($files->file('brand', '../config.yaml'))->toBeNull()
        ->and($files->browse('unknown'))->toBeNull();
});

it('lists a symlink without following it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $outside = sys_get_temp_dir().'/larapilot-outside-'.bin2hex(random_bytes(6));
    mkdir($outside);
    file_put_contents($outside.'/secret.txt', 'secret');

    $link = base_path('.larapilot/legacy/escape');

    if (! @symlink($outside, $link)) {
        @unlink($outside.'/secret.txt');
        @rmdir($outside);
        $this->markTestSkipped('Symlinks are not available on this filesystem.');
    }

    try {
        $this->get('/larapilot/files/legacy')->assertOk()->assertSee('escape', false);
        $this->get('/larapilot/files/legacy/escape')->assertNotFound();
        $this->get('/larapilot/files/legacy/escape/secret.txt')->assertNotFound();
        $this->get('/larapilot/files/raw/legacy/escape/secret.txt')->assertNotFound();

        $this->post('/larapilot/files/legacy/upload', [
            'path' => 'escape',
            'files' => [UploadedFile::fake()->createWithContent('in.txt', 'x')],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post('/larapilot/files/legacy/delete', ['path' => 'escape'])
            ->assertSessionHas('larapilot_success');

        expect(is_link($link))->toBeFalse()
            ->and(file_get_contents($outside.'/secret.txt'))->toBe('secret')
            ->and(file_exists($outside.'/in.txt'))->toBeFalse();
    } finally {
        @unlink($link);
        @unlink($outside.'/secret.txt');
        @rmdir($outside);
    }
});

it('serves images inline and everything that can run as a download', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('brand/logo.png', 'png-bytes');
    material('brand/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    material('legacy/index.html', '<script>alert(1)</script>');

    $image = $this->get('/larapilot/files/raw/brand/logo.png')->assertOk();

    expect($image->headers->get('Content-Type'))->toBe('image/png')
        ->and($image->headers->get('Content-Disposition'))->toContain('inline')
        ->and($image->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $svg = $this->get('/larapilot/files/raw/brand/mark.svg')->assertOk();

    expect($svg->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($svg->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");

    $html = $this->get('/larapilot/files/raw/legacy/index.html')->assertOk();

    expect($html->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($html->headers->get('Content-Disposition'))->toContain('attachment');

    $forced = $this->get('/larapilot/files/raw/brand/logo.png?download=1')->assertOk();

    expect($forced->headers->get('Content-Disposition'))->toContain('attachment');

    $this->get('/larapilot/files/legacy/index.html')
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('marks the packaged design systems', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('design-systems/acme/tokens.css', ':root{}');

    $html = $this->get('/larapilot/files/design-systems')
        ->assertOk()
        ->assertSee('filament', false)
        ->assertSee('acme', false)
        ->assertSee('Packaged', false)
        ->getContent();

    expect(substr_count($html, '>Packaged</span>'))->toBe(5);
});

it('keeps the Boost registration of a custom skill in step', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $skill = "---\nname: staging-gate\ndescription: Confirm staging is green before promote.\n---\n\n# Staging gate\n";

    $this->post('/larapilot/files/skills/upload', [
        'files' => [
            UploadedFile::fake()->createWithContent('SKILL.md', $skill),
            UploadedFile::fake()->createWithContent('checklist.md', '- [ ] green'),
        ],
        'paths' => ['staging-gate/SKILL.md', 'staging-gate/checklist.md'],
    ], ['Accept' => 'application/json'])->assertOk();

    expect(base_path('.ai/skills/staging-gate/SKILL.md'))->toBeFile()
        ->and(base_path('.ai/skills/staging-gate/checklist.md'))->toBeFile();

    $this->post('/larapilot/files/skills/delete', ['path' => 'staging-gate/checklist.md'])
        ->assertRedirect('/larapilot/files/skills/staging-gate');

    expect(base_path('.ai/skills/staging-gate/SKILL.md'))->toBeFile()
        ->and(file_exists(base_path('.ai/skills/staging-gate/checklist.md')))->toBeFalse();

    $this->post('/larapilot/files/skills/delete', ['path' => 'staging-gate'])
        ->assertRedirect('/larapilot/files/skills');

    expect(is_dir(base_path('.larapilot/skills/staging-gate')))->toBeFalse()
        ->and(is_dir(base_path('.ai/skills/staging-gate')))->toBeFalse()
        ->and(base_path('.larapilot/skills/.gitkeep'))->toBeFile();
});

it('leaves a registered copy that is not the skill it mirrors', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('skills/deploy/SKILL.md', "---\nname: deploy\ndescription: Ours.\n---\n");

    mkdir(base_path('.ai/skills/deploy'), 0755, true);
    file_put_contents(base_path('.ai/skills/deploy/SKILL.md'), "---\nname: deploy\ndescription: Someone else's.\n---\n");

    $this->post('/larapilot/files/skills/delete', ['path' => 'deploy'])
        ->assertRedirect('/larapilot/files/skills')
        ->assertSessionHas('larapilot_details');

    expect(is_dir(base_path('.larapilot/skills/deploy')))->toBeFalse()
        ->and(file_get_contents(base_path('.ai/skills/deploy/SKILL.md')))->toContain("Someone else's.");
});

it('stays behind the dashboard sign-in on a shared environment', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('brand/logo.png', 'png');
    material('client-materials/contract.md', '# Confidential');

    $this->app['env'] = 'staging';

    $this->get('/larapilot')->assertOk()->assertDontSee('>File manager</a>', false);
    $this->get('/larapilot/files')->assertNotFound();
    $this->get('/larapilot/files/client-materials')->assertNotFound();
    $this->get('/larapilot/files/client-materials/contract.md')->assertNotFound();
    $this->get('/larapilot/files/raw/client-materials/contract.md')->assertNotFound();
    $this->post('/larapilot/files/brand/delete', signed(['path' => 'logo.png']))->assertNotFound();
    $this->post('/larapilot/files/brand/rename', signed(['path' => 'logo.png', 'name' => 'x.png']))->assertNotFound();
    $this->post('/larapilot/files/brand/folder', signed(['name' => 'x']))->assertNotFound();
    $this->post('/larapilot/files/brand/upload', signed([
        'files' => [UploadedFile::fake()->createWithContent('x.txt', 'x')],
    ]))->assertNotFound();

    expect(base_path('.larapilot/brand/logo.png'))->toBeFile()
        ->and(file_exists(base_path('.larapilot/brand/x.txt')))->toBeFalse();

    app(DashboardAuthService::class)->setUser('andrea', 's3cret-pass');
    app(ConfigService::class)->updateSettings(['dashboard_auth' => 'YES']);

    $headers = ['Authorization' => 'Basic '.base64_encode('andrea:s3cret-pass')];

    $this->get('/larapilot/files/brand')->assertStatus(401);
    $this->get('/larapilot/files/raw/brand/logo.png')->assertStatus(401);
    $this->post('/larapilot/files/brand/delete', signed(['path' => 'logo.png']))->assertStatus(401);

    $this->get('/larapilot', $headers)->assertOk()->assertSee('>File manager</a>', false);

    $this->get('/larapilot/files/brand', $headers)
        ->assertOk()
        ->assertSee('Upload files', false);

    $this->post('/larapilot/files/brand/delete', signed(['path' => 'logo.png']), $headers)
        ->assertRedirect('/larapilot/files/brand');

    expect(file_exists(base_path('.larapilot/brand/logo.png')))->toBeFalse();
});

it('hides the file manager in production and when it is switched off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('brand/logo.png', 'png');

    config()->set('larapilot.file_manager.enabled', false);

    $this->get('/larapilot/files')->assertNotFound();
    $this->get('/larapilot/files/brand')->assertNotFound();
    $this->get('/larapilot/files/raw/brand/logo.png')->assertNotFound();
    $this->post('/larapilot/files/brand/delete', ['path' => 'logo.png'])->assertNotFound();
    $this->get('/larapilot')->assertOk()->assertDontSee('>File manager</a>', false);

    config()->set('larapilot.file_manager.enabled', true);
    $this->app['env'] = 'production';

    $this->get('/larapilot/files')->assertNotFound();
    $this->get('/larapilot/files/brand')->assertNotFound();
    $this->get('/larapilot/files/raw/brand/logo.png')->assertNotFound();
    $this->post('/larapilot/files/brand/delete', signed(['path' => 'logo.png']))->assertNotFound();

    expect(base_path('.larapilot/brand/logo.png'))->toBeFile();
});

it('follows a material path moved in the project config', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig(['paths' => ['client_materials' => 'intake/client/']]);

    mkdir(base_path('intake/client'), 0755, true);
    file_put_contents(base_path('intake/client/brief.md'), '# Brief');

    try {
        $this->get('/larapilot/files')
            ->assertOk()
            ->assertSee('intake/client/', false);

        $this->get('/larapilot/files/client-materials')
            ->assertOk()
            ->assertSee('brief.md', false);
    } finally {
        @unlink(base_path('intake/client/brief.md'));
        @unlink(base_path('intake/client/.gitkeep'));
        @rmdir(base_path('intake/client'));
        @unlink(base_path('intake/.gitkeep'));
        @rmdir(base_path('intake'));
    }
});

it('shows a folder that does not exist yet as empty, without creating it', function (): void {
    app(ConfigService::class)->writeProjectConfig();

    $brand = base_path('.larapilot/brand');
    @unlink($brand.'/.gitkeep');
    rmdir($brand);

    $this->get('/larapilot/files')->assertOk()->assertSee('Empty', false);

    $this->get('/larapilot/files/brand')
        ->assertOk()
        ->assertSee('This folder is empty.', false);

    expect(is_dir($brand))->toBeFalse();

    $this->get('/larapilot/files/brand/missing')->assertNotFound();

    $this->post('/larapilot/files/brand/upload', [
        'files' => [UploadedFile::fake()->createWithContent('logo.svg', '<svg/>')],
    ], ['Accept' => 'application/json'])->assertOk();

    expect($brand.'/logo.svg')->toBeFile();
});

/**
 * A scratch folder at the top of the project, gone when the test ends: the
 * application the tests run in is shared and lives under vendor/.
 *
 * @param  callable(string $probe): void  $callback
 */
function withProjectProbe(callable $callback): void
{
    $probe = base_path('lp-probe');

    if (is_dir($probe)) {
        shell_exec('rm -rf '.escapeshellarg($probe));
    }

    mkdir($probe.'/deep/deeper', 0755, true);

    try {
        $callback($probe);
    } finally {
        shell_exec('rm -rf '.escapeshellarg($probe));
    }
}

it('browses the project without the folders that start with a dot', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withProjectProbe(function (string $probe): void {
        file_put_contents($probe.'/notes.md', "# Notes\n\nFrom the project.");
        file_put_contents($probe.'/.editorconfig', "root = true\n");
        file_put_contents($probe.'/.gitignore', "/vendor\n");

        foreach (['.git', '.github/workflows', '.idea', '.cache/deep'] as $folder) {
            mkdir($probe.'/'.$folder, 0755, true);
            file_put_contents($probe.'/'.$folder.'/inside.txt', 'hidden with its folder');
        }

        expect(is_dir(base_path('.larapilot')))->toBeTrue()
            ->and(is_dir(base_path('.git')))->toBeTrue();

        $top = $this->get('/larapilot/files/project')
            ->assertOk()
            ->assertSee('Project', false)
            ->assertSee('Read only', false)
            ->assertSee('/larapilot/files/project/lp-probe', false)
            ->assertSee('/larapilot/files/project/artisan', false)
            // a file that starts with a dot is a project file like any other
            ->assertSee('/larapilot/files/project/.env.example', false)
            ->assertSee('/larapilot/files/project/.gitignore', false)
            // nothing on the page writes
            ->assertDontSee('data-upload-pick="files"', false)
            ->assertDontSee('class="dropzone"', false)
            ->assertDontSee('enctype="multipart/form-data"', false)
            ->assertDontSee('id="rename-dialog"', false)
            ->assertDontSee('id="delete-dialog"', false)
            ->assertDontSee('id="folder-dialog"', false)
            ->assertDontSee('data-dialog="rename-dialog"', false)
            ->assertDontSee('data-dialog="delete-dialog"', false)
            ->getContent();

        // No folder that starts with a dot is listed, whatever it is called.
        expect(preg_match('#/larapilot/files/project/\.(larapilot|git|github|idea|ai)(["/])#', $top))->toBe(0);

        // The same page of a material folder does write.
        $this->get('/larapilot/files/brand')
            ->assertOk()
            ->assertSee('data-upload-pick="files"', false)
            ->assertSee('id="folder-dialog"', false);

        $listing = $this->get('/larapilot/files/project/lp-probe')
            ->assertOk()
            ->assertSee('notes.md', false)
            ->assertSee('deep', false)
            ->assertSee('/larapilot/files/project/lp-probe/.editorconfig', false)
            ->assertSee('/larapilot/files/project/lp-probe/.gitignore', false)
            ->getContent();

        expect(preg_match('#/larapilot/files/project/lp-probe/\.(git|github|idea|cache)(["/])#', $listing))->toBe(0)
            ->and($listing)->toContain('1 folder');

        $this->get('/larapilot/files/project/lp-probe/notes.md')
            ->assertOk()
            ->assertSee('From the project.', false)
            ->assertSee('Download', false)
            ->assertDontSee('data-dialog="rename-dialog"', false)
            ->assertDontSee('data-dialog="delete-dialog"', false);

        $this->get('/larapilot/files/project/lp-probe/.editorconfig')
            ->assertOk()
            ->assertSee('root = true', false);

        $this->get('/larapilot/files/raw/project/lp-probe/notes.md')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        foreach ([
            '/larapilot/files/project/.larapilot',
            '/larapilot/files/project/.larapilot/config.yaml',
            '/larapilot/files/raw/project/.larapilot/config.yaml',
            '/larapilot/files/project/.git',
            '/larapilot/files/raw/project/.git/HEAD',
            '/larapilot/files/project/lp-probe/.git',
            '/larapilot/files/raw/project/lp-probe/.git/inside.txt',
            '/larapilot/files/project/lp-probe/.github',
            '/larapilot/files/project/lp-probe/.github/workflows',
            '/larapilot/files/raw/project/lp-probe/.github/workflows/inside.txt',
            '/larapilot/files/project/lp-probe/.idea/inside.txt',
            '/larapilot/files/raw/project/lp-probe/.cache/deep/inside.txt',
            '/larapilot/files/project/lp-probe/../.larapilot/config.yaml',
            '/larapilot/files/project/lp-probe/%2e%2e/.larapilot/config.yaml',
        ] as $url) {
            expect($this->get($url)->getStatusCode())->toBe(404, $url);
        }

        // A material folder keeps the folders it was given, dot or not.
        material('legacy/old/.well-known/security.txt', 'Contact: mailto:a@example.test');
        $this->get('/larapilot/files/legacy/old')->assertOk()->assertSee('.well-known', false);
        $this->get('/larapilot/files/legacy/old/.well-known/security.txt')->assertOk()->assertSee('Contact:', false);
    });
});

it('shows a file that holds credentials with every value hidden', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withProjectProbe(function (string $probe): void {
        $mask = FileManagerService::MASK;

        expect($mask)->toBe('*****************');

        file_put_contents($probe.'/.env', implode("\n", [
            '# Application',
            'APP_NAME="Fjord Invoices"',
            'APP_KEY=base64:do-not-leak-1',
            'export STRIPE_SECRET=sk_live_do-not-leak-2',
            'EMPTY_ONE=',
            'MULTI="first do-not-leak-3',
            'second do-not-leak-4"',
            '#OLD_KEY=do-not-leak-5',
            '',
            'DB_PASSWORD = do-not-leak-6',
        ]));
        file_put_contents($probe.'/.env.production', 'DB_PASSWORD=do-not-leak');
        file_put_contents($probe.'/.env.example', 'APP_KEY=');
        file_put_contents($probe.'/auth.json', json_encode([
            'http-basic' => ['repo.example.test' => ['username' => 'do-not-leak-user', 'password' => 'do-not-leak-pass']],
            'github-oauth' => ['github.com' => 'do-not-leak-token'],
            'bearer' => [],
        ]));
        file_put_contents($probe.'/.npmrc', "registry=https://registry.example.test/\n//registry.example.test/:_authToken=do-not-leak\n");
        file_put_contents($probe.'/.netrc', "machine example.test login do-not-leak-user password do-not-leak-pass\n");
        file_put_contents($probe.'/.pgpass', "db.example.test:5432:app:do-not-leak-user:do-not-leak-pass\n");
        file_put_contents($probe.'/oauth-private.key', "-----BEGIN PRIVATE KEY-----\ndo-not-leak\n-----END PRIVATE KEY-----\n");
        file_put_contents($probe.'/database.sqlite', 'do-not-leak');

        $this->get('/larapilot/files/project/lp-probe')
            ->assertOk()
            ->assertSee('.env.production', false)
            ->assertSee('oauth-private.key', false)
            ->assertSee('Values hidden', false)
            ->assertSee('Not shown', false)
            ->assertDontSee('do-not-leak', false);

        $env = $this->get('/larapilot/files/project/lp-probe/.env')
            ->assertOk()
            ->assertSee('This file holds credentials.', false)
            ->assertSee('Download, values hidden', false)
            ->assertDontSee('do-not-leak', false)
            ->assertDontSee('Fjord Invoices', false)
            ->getContent();

        // The names of the keys are there, and so are the comments.
        foreach (['# Application', 'APP_NAME='.$mask, 'APP_KEY='.$mask, 'export STRIPE_SECRET='.$mask, 'EMPTY_ONE='.$mask, 'MULTI='.$mask, '#OLD_KEY='.$mask, 'DB_PASSWORD ='.$mask] as $line) {
            expect($env)->toContain(e($line));
        }

        $download = $this->get('/larapilot/files/raw/project/lp-probe/.env?download=1')->assertOk();

        expect($download->headers->get('Content-Disposition'))->toBe('attachment; filename=".env"')
            ->and($download->headers->get('Content-Type'))->toBe('text/plain; charset=UTF-8')
            ->and($download->getContent())->toContain('APP_KEY='.$mask)
            ->and($download->getContent())->not->toContain('do-not-leak')
            // eight lines carry a value; the second line of the value written
            // over two is masked whole
            ->and(substr_count($download->getContent(), $mask))->toBe(8)
            ->and($download->getContent())->toContain("MULTI={$mask}\n{$mask}\n");

        // Without ?download the answer is the same: never the bytes on disk.
        expect($this->get('/larapilot/files/raw/project/lp-probe/.env')->getContent())->not->toContain('do-not-leak');

        $auth = json_decode($this->get('/larapilot/files/raw/project/lp-probe/auth.json')->assertOk()->getContent(), true);

        expect($auth)->toBe([
            'http-basic' => ['repo.example.test' => ['username' => $mask, 'password' => $mask]],
            'github-oauth' => ['github.com' => $mask],
            'bearer' => $mask,
        ]);

        $this->get('/larapilot/files/project/lp-probe/auth.json')
            ->assertOk()
            ->assertSee('http-basic', false)
            ->assertSee('username', false)
            ->assertDontSee('do-not-leak', false);

        $files = app(FileManagerService::class);

        expect($files->masked('project', 'lp-probe/.npmrc'))->toBe("registry={$mask}\n//registry.example.test/:_authToken={$mask}\n")
            ->and($files->masked('project', 'lp-probe/.netrc'))->toBe("machine example.test login {$mask} password {$mask}\n")
            ->and($files->masked('project', 'lp-probe/.pgpass'))->toBe("db.example.test:5432:app:{$mask}\n")
            ->and($files->masked('project', 'lp-probe/oauth-private.key'))->toBe($mask."\n")
            ->and($files->masked('project', 'lp-probe/.env.production'))->toBe('DB_PASSWORD='.$mask)
            // a template and an ordinary file are not masked
            ->and($files->masked('project', 'lp-probe/.env.example'))->toBeNull()
            ->and($files->masked('project', 'lp-probe/missing'))->toBeNull();

        foreach (['.env', '.env.production', 'auth.json', '.npmrc', '.netrc', '.pgpass', 'oauth-private.key', 'database.sqlite'] as $name) {
            // The path of the real bytes is never handed out.
            expect($files->file('project', 'lp-probe/'.$name))->toBeNull($name)
                ->and($this->get('/larapilot/files/project/lp-probe/'.$name)->assertOk()->getContent())->not->toContain('do-not-leak');
        }

        // A database is neither shown nor served.
        $this->get('/larapilot/files/project/lp-probe/database.sqlite')
            ->assertOk()
            ->assertSee('is a database, so it is neither shown nor downloaded from the dashboard.', false)
            ->assertDontSee('/larapilot/files/raw/', false);

        expect($this->get('/larapilot/files/raw/project/lp-probe/database.sqlite')->getStatusCode())->toBe(404);

        $this->get('/larapilot/files/project/lp-probe/.env.example')
            ->assertOk()
            ->assertSee('APP_KEY=', false)
            ->assertDontSee('This file holds credentials.', false);

        // The env file at the top of the project. The testbench app does not
        // ship one, so the test places it and puts back whatever was there.
        $rootEnv = base_path('.env');
        $previous = is_file($rootEnv) ? file_get_contents($rootEnv) : false;
        file_put_contents($rootEnv, "APP_NAME=Fjord\nAPP_KEY=base64:do-not-leak-root\n");

        try {
            expect($this->get('/larapilot/files/raw/project/.env')->assertOk()->getContent())
                ->toContain('APP_KEY='.$mask)
                ->not->toContain('do-not-leak');
        } finally {
            if ($previous === false) {
                @unlink($rootEnv);
            } else {
                file_put_contents($rootEnv, $previous);
            }
        }

        // The material folders mask the same files.
        material('legacy/old/.env', 'LEGACY_TOKEN=do-not-leak');

        $this->get('/larapilot/files/legacy/old/.env')
            ->assertOk()
            ->assertSee('LEGACY_TOKEN='.$mask, false)
            ->assertDontSee('do-not-leak', false);

        expect($this->get('/larapilot/files/raw/legacy/old/.env?download=1')->getContent())->toBe('LEGACY_TOKEN='.$mask);
    });
});

it('refuses every write to the project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withProjectProbe(function (string $probe): void {
        file_put_contents($probe.'/keep.txt', 'kept');

        $writes = [
            ['/larapilot/files/project/upload', ['path' => 'lp-probe', 'files' => [UploadedFile::fake()->createWithContent('shell.php', '<?php')]]],
            ['/larapilot/files/project/folder', ['path' => 'lp-probe', 'name' => 'made']],
            ['/larapilot/files/project/rename', ['path' => 'lp-probe/keep.txt', 'name' => 'moved.txt']],
            ['/larapilot/files/project/delete', ['path' => 'lp-probe/keep.txt']],
        ];

        foreach ($writes as [$url, $data]) {
            expect($this->post($url, signed($data))->getStatusCode())->toBeIn([404, 405], $url);
        }

        $files = app(FileManagerService::class);

        expect(fn () => $files->upload('project', 'lp-probe', [UploadedFile::fake()->createWithContent('shell.php', '<?php')]))
            ->toThrow(InvalidArgumentException::class, 'read only')
            ->and(fn () => $files->createFolder('project', 'lp-probe', 'made'))
            ->toThrow(InvalidArgumentException::class, 'read only')
            ->and(fn () => $files->rename('project', 'lp-probe/keep.txt', 'moved.txt'))
            ->toThrow(InvalidArgumentException::class, 'read only')
            ->and(fn () => $files->delete('project', 'lp-probe/keep.txt'))
            ->toThrow(InvalidArgumentException::class, 'read only');

        expect(file_get_contents($probe.'/keep.txt'))->toBe('kept')
            ->and(is_file($probe.'/shell.php'))->toBeFalse()
            ->and(is_dir($probe.'/made'))->toBeFalse()
            ->and($files->writableRootKeys())->toBe(['brand', 'client-materials', 'design-systems', 'legacy', 'skills']);
    });
});

it('does not reach .larapilot through a link inside the project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withProjectProbe(function (string $probe): void {
        symlink(base_path('.larapilot'), $probe.'/state');

        $this->get('/larapilot/files/project/lp-probe')
            ->assertOk()
            ->assertSee('state', false);

        foreach ([
            '/larapilot/files/project/lp-probe/state',
            '/larapilot/files/project/lp-probe/state/config.yaml',
            '/larapilot/files/raw/project/lp-probe/state/config.yaml',
        ] as $url) {
            expect($this->get($url)->getStatusCode())->toBe(404, $url);
        }
    });
});

it('unfolds the project tree along the open folder only', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withProjectProbe(function (string $probe): void {
        mkdir($probe.'/sibling/inside', 0755, true);

        $data = app(FileManagerService::class)->browse('project', 'lp-probe/deep');
        $top = collect($data['tree']['nodes'])->keyBy('name');

        expect($top->has('lp-probe'))->toBeTrue()
            ->and($top->has('app'))->toBeTrue()
            ->and($top->has('.larapilot'))->toBeFalse()
            ->and($top->has('.git'))->toBeFalse()
            ->and($top->keys()->filter(fn (string $name): bool => str_starts_with($name, '.'))->all())->toBe([])
            // a folder off the path stays folded, whatever it holds
            ->and($top['app']['children'])->toBe([])
            ->and($top['lp-probe']['open'])->toBeTrue();

        $probeChildren = collect($top['lp-probe']['children'])->keyBy('name');

        expect($probeChildren->keys()->all())->toBe(['deep', 'sibling'])
            ->and($probeChildren['deep']['active'])->toBeTrue()
            ->and(collect($probeChildren['deep']['children'])->pluck('name')->all())->toBe(['deeper'])
            ->and($probeChildren['sibling']['children'])->toBe([]);

        // A material folder is still unfolded whole.
        material('brand/logos/dark/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $brand = app(FileManagerService::class)->browse('brand');

        expect($brand['tree']['nodes'][0]['name'])->toBe('logos')
            ->and($brand['tree']['nodes'][0]['children'][0]['name'])->toBe('dark');
    });
});

it('counts the project without vendor, node_modules, and the folders that start with a dot', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withProjectProbe(function (string $probe): void {
        mkdir($probe.'/vendor/pkg', 0755, true);
        mkdir($probe.'/node_modules/pkg', 0755, true);
        file_put_contents($probe.'/vendor/pkg/big.bin', str_repeat('v', 4096));
        file_put_contents($probe.'/node_modules/pkg/big.bin', str_repeat('n', 4096));
        mkdir($probe.'/.git/objects', 0755, true);
        file_put_contents($probe.'/.git/objects/pack.bin', str_repeat('g', 4096));
        file_put_contents($probe.'/.hidden-file', 'dot');
        file_put_contents($probe.'/counted.txt', 'counted');

        $measure = (new ReflectionClass(FileManagerService::class))->getMethod('measure');
        $measure->setAccessible(true);

        $whole = $measure->invoke(app(FileManagerService::class), $probe, false);
        $project = $measure->invoke(app(FileManagerService::class), $probe, true);

        expect($whole['bytes'])->toBe(4096 * 3 + 3 + 7)
            // the file that starts with a dot counts, the folder does not
            ->and($project['bytes'])->toBe(3 + 7)
            ->and($project['files'])->toBe(2)
            // deep/ and deep/deeper/ — vendor/, node_modules/ and .git/ are not walked
            ->and($project['folders'])->toBe(2);

        $top = $measure->invoke(app(FileManagerService::class), base_path(), true);
        $state = $measure->invoke(app(FileManagerService::class), base_path('.larapilot'), false);

        expect($state['files'])->toBeGreaterThan(10)
            ->and($top['truncated'])->toBeFalse();
    });
});

it('reads a PDF in the page instead of sending it to its own tab', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    material('client-materials/brief.pdf', "%PDF-1.4\n%%EOF");
    material('client-materials/notes.md', '# Notes');

    $html = $this->get('/larapilot/files/client-materials/brief.pdf')
        ->assertOk()
        ->assertSee('id="pdf-reader"', false)
        ->assertSee('data-src="'.url('/larapilot/files/raw/client-materials/brief.pdf').'"', false)
        ->assertSee('role="toolbar"', false)
        ->assertSee('Two pages side by side', false)
        ->assertSee('Zoom in', false)
        ->assertSee('Previous page', false)
        ->assertSee('Full screen', false)
        ->assertSee('Download brief.pdf', false)
        // without scripts, or without the library, the file still opens
        ->assertSee('<noscript>', false)
        ->assertSee('The reader could not start here, so the PDF opens in its own tab.', false)
        ->getContent();

    // The library is pinned to a version and checked against its hash.
    expect($html)->toMatch('#<script src="https://cdnjs\.cloudflare\.com/ajax/libs/pdf\.js/3\.11\.174/pdf\.min\.js" integrity="sha384-[A-Za-z0-9+/=]+" crossorigin="anonymous"#')
        ->toContain('data-worker="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js"');

    // The bytes the reader asks for are served inline, as a PDF.
    $raw = $this->get('/larapilot/files/raw/client-materials/brief.pdf')->assertOk();

    expect($raw->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($raw->headers->get('Content-Disposition'))->toStartWith('inline');

    // Any other file has no reader on its page.
    $this->get('/larapilot/files/client-materials/notes.md')
        ->assertOk()
        ->assertDontSee('id="pdf-reader"', false)
        ->assertDontSee('pdf.min.js', false);
});

it('adds, renames, and deletes in the five material folders and nowhere else', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $files = app(FileManagerService::class);

    expect($files->writableRootKeys())->toBe(['brand', 'client-materials', 'design-systems', 'legacy', 'skills'])
        ->and(array_keys(array_filter($files->roots(), fn (array $root): bool => $root['read_only'])))->toBe(['project']);

    $folders = [
        'brand' => '.larapilot/brand',
        'client-materials' => '.larapilot/client-materials',
        'design-systems' => '.larapilot/design-systems',
        'legacy' => '.larapilot/legacy',
        'skills' => '.larapilot/skills',
    ];

    foreach ($folders as $root => $path) {
        $base = base_path($path);

        // a folder is added
        $this->post('/larapilot/files/'.$root.'/folder', signed(['path' => '', 'name' => 'probe']))
            ->assertRedirect()
            ->assertSessionHas('larapilot_success');
        expect(is_dir($base.'/probe'))->toBeTrue($root.': folder');

        // a file is added
        $this->post('/larapilot/files/'.$root.'/upload', signed([
            'path' => 'probe',
            'files' => [UploadedFile::fake()->createWithContent('note.txt', 'kept')],
        ]))->assertRedirect()->assertSessionHas('larapilot_success');
        expect(is_file($base.'/probe/note.txt'))->toBeTrue($root.': upload');

        // a file and a folder are renamed
        $this->post('/larapilot/files/'.$root.'/rename', signed(['path' => 'probe/note.txt', 'name' => 'renamed.txt']))
            ->assertRedirect()
            ->assertSessionHas('larapilot_success');
        $this->post('/larapilot/files/'.$root.'/rename', signed(['path' => 'probe', 'name' => 'moved']))
            ->assertRedirect()
            ->assertSessionHas('larapilot_success');
        expect(is_file($base.'/moved/renamed.txt'))->toBeTrue($root.': rename')
            ->and(is_dir($base.'/probe'))->toBeFalse($root.': rename');

        // a file and a folder are deleted
        $this->post('/larapilot/files/'.$root.'/delete', signed(['path' => 'moved/renamed.txt']))
            ->assertRedirect()
            ->assertSessionHas('larapilot_success');
        $this->post('/larapilot/files/'.$root.'/delete', signed(['path' => 'moved']))
            ->assertRedirect()
            ->assertSessionHas('larapilot_success');
        expect(file_exists($base.'/moved'))->toBeFalse($root.': delete');

        // and the page offers all of it
        $this->get('/larapilot/files/'.$root)
            ->assertOk()
            ->assertSee('data-upload-pick="files"', false)
            ->assertSee('data-upload-pick="folder"', false)
            ->assertSee('id="folder-dialog"', false)
            ->assertSee('id="rename-dialog"', false)
            ->assertSee('id="delete-dialog"', false);
    }

    // The project is read: the same requests change nothing there.
    withProjectProbe(function (string $probe): void {
        file_put_contents($probe.'/keep.txt', 'kept');
        $before = scandir($probe);

        foreach ([
            ['folder', ['path' => 'lp-probe', 'name' => 'made']],
            ['upload', ['path' => 'lp-probe', 'files' => [UploadedFile::fake()->createWithContent('added.txt', 'x')]]],
            ['rename', ['path' => 'lp-probe/keep.txt', 'name' => 'moved.txt']],
            ['rename', ['path' => 'lp-probe', 'name' => 'lp-moved']],
            ['delete', ['path' => 'lp-probe/keep.txt']],
            ['delete', ['path' => 'lp-probe']],
        ] as [$action, $data]) {
            expect($this->post('/larapilot/files/project/'.$action, signed($data))->getStatusCode())->toBeIn([404, 405], $action);
            expect($this->postJson('/larapilot/files/project/'.$action, signed($data))->getStatusCode())->toBeIn([404, 405], $action.' (json)');
        }

        expect(scandir($probe))->toBe($before)
            ->and(file_get_contents($probe.'/keep.txt'))->toBe('kept')
            ->and(is_dir(base_path('lp-moved')))->toBeFalse();
    });
});

it('redacts a log of the project the way the Logs page does', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withProjectProbe(function (string $probe): void {
        mkdir($probe.'/storage/logs', 0755, true);
        file_put_contents($probe.'/storage/logs/laravel.log', implode("\n", [
            '[2026-10-01 10:00:00] production.ERROR: Payment failed for order 41 {"password":"do-not-leak-1","order":41}',
            '[2026-10-01 10:00:01] production.INFO: Authorization: Bearer do-not-leak-2',
            '[2026-10-01 10:00:02] production.INFO: APP_KEY=base64:do-not-leak-3-aaaaaaaaaaaaaaaaaaaaaaaa',
            '[2026-10-01 10:00:03] production.INFO: Order 41 shipped',
            '',
        ]));

        $this->get('/larapilot/files/project/lp-probe/storage/logs')
            ->assertOk()
            ->assertSee('laravel.log', false)
            ->assertSee('Secrets redacted', false)
            ->assertDontSee('do-not-leak', false);

        $this->get('/larapilot/files/project/lp-probe/storage/logs/laravel.log')
            ->assertOk()
            ->assertSee('Payment failed for order 41', false)
            ->assertSee('Order 41 shipped', false)
            ->assertSee('[REDACTED]', false)
            ->assertSee('This is a log.', false)
            ->assertDontSee('do-not-leak', false);

        $download = $this->get('/larapilot/files/raw/project/lp-probe/storage/logs/laravel.log?download=1')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        expect($download->headers->get('Content-Disposition'))->toContain('attachment')
            ->and($download->getContent())->toContain('Order 41 shipped')
            ->and($download->getContent())->toContain('[REDACTED]')
            ->and($download->getContent())->not->toContain('do-not-leak');
    });
});

it('leaves the caches of the framework out of the project', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    withProjectProbe(function (string $probe): void {
        foreach (['bootstrap/cache', 'storage/framework/sessions', 'storage/framework/cache/data'] as $folder) {
            mkdir($probe.'/'.$folder, 0755, true);
            file_put_contents($probe.'/'.$folder.'/inside.php', '<?php return ["key" => "do-not-leak"];');
        }

        mkdir($probe.'/bootstrap/providers', 0755, true);
        file_put_contents($probe.'/bootstrap/providers/app.php', '<?php return [];');
        mkdir($probe.'/storage/app', 0755, true);
        file_put_contents($probe.'/storage/app/upload.txt', 'an upload');

        $this->get('/larapilot/files/project/lp-probe/bootstrap')
            ->assertOk()
            ->assertSee('providers', false)
            ->assertDontSee('>cache<', false);

        $this->get('/larapilot/files/project/lp-probe/storage')
            ->assertOk()
            ->assertSee('>app<', false)
            ->assertDontSee('framework', false);

        foreach ([
            '/larapilot/files/project/lp-probe/bootstrap/cache',
            '/larapilot/files/project/lp-probe/bootstrap/cache/inside.php',
            '/larapilot/files/raw/project/lp-probe/bootstrap/cache/inside.php',
            '/larapilot/files/project/lp-probe/storage/framework',
            '/larapilot/files/project/lp-probe/storage/framework/sessions/inside.php',
            '/larapilot/files/raw/project/lp-probe/storage/framework/cache/data/inside.php',
        ] as $url) {
            expect($this->get($url)->getStatusCode())->toBe(404, $url);
        }

        $this->get('/larapilot/files/raw/project/lp-probe/storage/app/upload.txt?download=1')->assertOk();
    });
});

it('keeps the zeros of a whole size', function (): void {
    $files = app(FileManagerService::class);

    expect($files->formatBytes(512))->toBe('512 B')
        ->and($files->formatBytes(1536))->toBe('1.5 KB')
        ->and($files->formatBytes(10240))->toBe('10 KB')
        ->and($files->formatBytes(20480))->toBe('20 KB')
        ->and($files->formatBytes(104857600))->toBe('100 MB')
        ->and($files->formatBytes(2 * 1024 ** 3))->toBe('2 GB')
        ->and($files->formatBytes(1024 ** 4))->toBe('1,024 GB');
});
