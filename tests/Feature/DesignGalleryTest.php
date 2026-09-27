<?php

declare(strict_types=1);

use Larapilot\Services\PrdService;
use Symfony\Component\Yaml\Yaml;

it('opens the design page on a gallery of every screen', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['title' => 'Login']);
    addMockup('US-001', [
        'index.html' => '<html><body>Login mockup</body></html>',
        'dark.html' => '<html><body>Dark login</body></html>',
        'styles.css' => 'body { color: navy; }',
    ]);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('>Design</a>', false);

    $response = $this->get('/larapilot/design')
        ->assertOk()
        ->assertSee('Design', false)
        ->assertSee('Presentation index', false)
        ->assertSee('Every mockup screen for', false)
        ->assertSee('Click a screen to open it as a site you can browse', false)
        ->assertSee('Open index in new tab', false)
        ->assertSee('US-001', false)
        ->assertSee('Login', false)
        ->assertSee('Download zip', false)
        ->assertSee('/larapilot/design/package.zip', false)
        ->assertSee('/mockups/US-001', false)
        ->assertSee('/mockups/US-001/dark.html', false)
        // the way back, the prev / next walk, the screens of the same flow
        ->assertSee('id="design-back"', false)
        ->assertSee('All screens', false)
        ->assertSee('design-prev', false)
        ->assertSee('design-next', false)
        ->assertSee('design-counter', false)
        ->assertSee('flow-gallery', false)
        ->assertSee('screen-grid', false)
        ->assertSee('All 2 screens, flow by flow', false);

    $html = $response->getContent();

    // The page opens on the gallery, and every screen in it is a card that
    // leads to its mockup.
    expect($html)->toContain('id="design-page" data-view="gallery"')
        ->toContain('data-src="/mockups/US-001"')
        ->toContain('data-src="/mockups/US-001/dark.html"');

    // The viewer waits for a click: no screen is loaded into it up front.
    expect($html)->toContain('<iframe id="design-frame" class="design-frame" title="Design viewer"></iframe>');

    // The ordered walk holds the screens only, each flow led by its entry.
    $entryPosition = strpos($html, '"url":"\/mockups\/US-001"');
    $darkPosition = strpos($html, '"url":"\/mockups\/US-001\/dark.html"');

    expect($entryPosition)->not->toBeFalse()
        ->and($darkPosition)->not->toBeFalse()
        ->and($entryPosition)->toBeLessThan($darkPosition)
        ->and($html)->not->toContain('"url":"\/larapilot\/design\/presentation"');

    // The dashboard frames the index itself, so that one route allows
    // same-origin framing instead of the dashboard-wide DENY.
    $this->get('/larapilot/design/presentation')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
        ->assertSee('Design presentation', false)
        ->assertSee('Contents', false)
        ->assertSee('Start at the first screen', false)
        ->assertSee('2 screens · the first one is the entry point', false)
        ->assertSee('class="screen is-entry"', false)
        ->assertSee('US-001', false)
        ->assertSee('/mockups/US-001', false);
});

it('names a mockup folder that is not a spec once', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addMockup('admin-filament', [
        'index.html' => '<html><body>Dashboard</body></html>',
        'customers.html' => '<html><body>Customers</body></html>',
    ]);

    $this->get('/larapilot/design')
        ->assertOk()
        ->assertSee('<span class="flow-code">admin-filament</span>', false)
        ->assertSee('data-src="/mockups/admin-filament/customers.html"', false)
        ->assertDontSee('admin-filament — admin-filament', false);

    $this->get('/larapilot/design/presentation')
        ->assertOk()
        ->assertSee('<h2>admin-filament</h2>', false)
        ->assertDontSee('admin-filament — admin-filament', false);
});

it('downloads a zip of mockup html, assets, and the presentation index', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addMockup('US-001', [
        'index.html' => '<html><head><link rel="stylesheet" href="styles.css"></head><body>Home</body></html>',
        'styles.css' => 'body { background: white; }',
        'logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
    ]);

    $response = $this->get('/larapilot/design/package.zip');
    $response->assertOk()
        ->assertHeader('content-type', 'application/zip');

    $tmp = tempnam(sys_get_temp_dir(), 'lp-zip-test-');
    file_put_contents($tmp, $response->getContent());

    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue()
        ->and($zip->locateName('index.html'))->not->toBeFalse()
        ->and($zip->locateName('US-001/index.html'))->not->toBeFalse()
        ->and($zip->locateName('US-001/styles.css'))->not->toBeFalse()
        ->and($zip->locateName('US-001/logo.svg'))->not->toBeFalse();

    $index = $zip->getFromName('index.html');
    expect($index)->toContain('US-001/index.html')
        ->toContain('Contents');

    $zip->close();
    unlink($tmp);
});

it('lists mockup style variants and records the chosen direction', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['title' => 'Login']);

    addMockup('US-001', [
        'styles.yaml' => <<<'YAML'
styles:
  - id: filament
    label: Filament admin
  - id: nordic-minimal
    label: Nordic minimal
YAML,
        'styles/filament/index.html' => '<html><body>Filament login</body></html>',
        'styles/nordic-minimal/index.html' => '<html><body>Nordic login</body></html>',
    ]);

    $html = $this->get('/larapilot/design')
        ->assertOk()
        ->assertDontSee('Compare styles', false)
        ->assertSee('style-bar', false)
        ->assertSee('Filament admin', false)
        ->assertSee('Nordic minimal', false)
        ->getContent();

    expect($html)->toContain('nordic-minimal')
        ->and($html)->toMatch('#mockups/US-001/styles/filament#');

    $this->from('/larapilot/design')
        ->post('/larapilot/design/mockups/US-001/style', ['style' => 'filament'])
        ->assertRedirect('/larapilot/design')
        ->assertSessionHas('larapilot_success');

    $manifest = Yaml::parseFile(base_path('.larapilot/mockups/US-001/styles.yaml'));
    expect($manifest['chosen'] ?? null)->toBe('filament');

    $this->artisan('larapilot:mockup-choose-style', [
        'spec' => 'US-001',
        '--style' => 'nordic-minimal',
    ])->assertSuccessful();

    expect(Yaml::parseFile(base_path('.larapilot/mockups/US-001/styles.yaml'))['chosen'])->toBe('nordic-minimal');
});

it('shows an empty design gallery when no mockups exist', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $this->get('/larapilot/design')
        ->assertOk()
        ->assertSee('No designs yet', false)
        ->assertSee('/larapilot-design', false)
        ->assertDontSee('/larapilot/design/package.zip', false);
});

it('localizes the presentation index from the PRD language', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    addMockup('US-001', ['index.html' => '<html><body>Home</body></html>']);
    app(PrdService::class)->write(<<<'MD'
# Negozio

## Sintesi
Un negozio.

## Visione
Vendere.

## Personas utente
Clienti.

## Requisiti funzionali
- Catalogo

## Ambito MVP
Catalogo.

## Architettura tecnica
Laravel.
MD);

    $this->get('/larapilot/design/presentation')
        ->assertOk()
        ->assertSee('Presentazione design', false)
        ->assertSee('Sommario', false)
        ->assertSee('Inizia dalla prima schermata', false)
        ->assertSee('Negozio', false);
});

it('shows every style and links a feature folder to the stories in its readme', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['title' => 'Home']);
    addSpec(['code' => 'US-002', 'title' => 'Access']);

    addMockup('public-site', [
        'index.html' => '<html><body>Showcase</body></html>',
        'styles.yaml' => <<<'YAML'
styles:
  - id: nordic-minimal
    label: Nordic minimal
  - id: playful-brand
    label: Playful brand
YAML,
        'styles/nordic-minimal/index.html' => '<html><body>Nordic home</body></html>',
        'styles/playful-brand/index.html' => '<html><body>Playful home</body></html>',
        'README.md' => <<<'MD'
# Public site

**Traccia a:** US-001, US-002 (FR-003)

| File | Schermata | Spec |
| --- | --- | --- |
| `index.html` | Home | US-001 |
| `accesso.html` | Accesso | US-002 |
MD,
    ]);

    $flow = base_path('.larapilot/mockups/public-site/styles');
    file_put_contents($flow.'/nordic-minimal/accesso.html', '<html><body>Nordic access</body></html>');
    file_put_contents($flow.'/playful-brand/accesso.html', '<html><body>Playful access</body></html>');

    $html = $this->get('/larapilot/design')
        ->assertOk()
        ->assertSee('Playful brand', false)
        ->assertSee('Nordic minimal', false)
        ->assertSee('data-src="/mockups/public-site/styles/playful-brand/accesso.html"', false)
        ->assertSee('data-src="/mockups/public-site/styles/nordic-minimal/index.html"', false)
        ->assertSee('data-src="/mockups/public-site"', false)
        ->assertSee('href="/larapilot/specs/US-001"', false)
        ->assertSee('href="/larapilot/specs/US-002"', false)
        ->assertSee('All 5 screens, flow by flow', false)
        ->getContent();

    expect($html)->toContain('playful-brand')
        ->and($html)->not->toContain('FR-003');

    $this->get('/larapilot/specs/US-002')
        ->assertOk()
        ->assertSee('Mockups', false)
        ->assertSee('/mockups/public-site/styles/playful-brand/accesso.html', false)
        ->assertSee('/mockups/public-site/styles/nordic-minimal/accesso.html', false)
        ->assertSee('Playful brand', false)
        ->assertDontSee('/mockups/public-site/styles/playful-brand/index.html', false);

    $this->get('/larapilot')
        ->assertOk()
        ->assertSee('Mockup', false);
});

it('ships sign-in samples that draw the credential fields', function (): void {
    $root = dirname(__DIR__, 2).'/resources/larapilot/design-systems';
    $samples = array_merge(
        glob($root.'/*/html/login.html') ?: [],
        glob($root.'/*/html/auth-*.html') ?: [],
    );

    expect($samples)->not->toBeEmpty();

    foreach ($samples as $sample) {
        $html = (string) file_get_contents($sample);
        $name = basename(dirname($sample, 2)).'/'.basename($sample);

        // Nothing a password manager can latch on to: no form, no input that
        // takes text, and none of the old masked-field workarounds.
        foreach (['<form', 'demo-secret-field', 'data-1p-ignore', 'data-lpignore'] as $banned) {
            expect(str_contains($html, $banned))->toBeFalse($name.' still has '.$banned);
        }

        expect(preg_match('/<input\b(?![^>]*type="checkbox")/i', $html))->toBe(0, $name.' has an input that takes text');

        foreach (['role="img"', 'aria-label="Password field, filled in"', 'role="button"'] as $drawn) {
            expect(str_contains($html, $drawn))->toBeTrue($name.' is missing '.$drawn);
        }
    }

    // No packaged screen anywhere takes a password.
    foreach (glob($root.'/*/html/*.html') ?: [] as $screen) {
        expect(preg_match('/type="password"|autocomplete="(?:current|new)-password"/i', (string) file_get_contents($screen)))
            ->toBe(0, basename(dirname($screen, 2)).'/'.basename($screen).' takes a password');
    }
});

it('tells the design skill to draw credential fields instead of building them', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $skill = (string) file_get_contents($root.'/boost/skills/larapilot-design/SKILL.md');
    $runtime = (string) file_get_contents($root.'/larapilot/runtime-ux-1.md');

    expect($skill)->toContain('Sign-in screens — draw the fields, never build them (hard rule)')
        ->toContain('Never write an `<input>`, `<textarea>`, or `<form>` for credentials')
        ->toContain('No workaround counts.')
        ->toContain('Password managers')
        ->not->toContain('Access code** (')
        ->not->toContain('demo-secret-field');

    expect($runtime)->toContain('Never write an `<input>` or `<form>` for credentials')
        ->not->toContain('demo-secret-field');
});
