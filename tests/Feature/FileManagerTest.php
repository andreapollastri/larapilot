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
        ->assertSee('Your custom skills, one folder per slash command.', false);

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
