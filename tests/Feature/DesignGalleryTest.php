<?php

declare(strict_types=1);

use Larapilot\Services\PrdService;
use Symfony\Component\Yaml\Yaml;

it('serves the design gallery as one navigable index', function (): void {
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
        ->assertSee('Open index in new tab', false)
        ->assertSee('US-001', false)
        ->assertSee('Login', false)
        ->assertSee('Download zip', false)
        ->assertSee('/larapilot/design/package.zip', false)
        ->assertSee('/mockups/US-001', false)
        ->assertSee('/mockups/US-001/dark.html', false)
        // prev / next walk, contextual flow gallery, full preview grid
        ->assertSee('design-prev', false)
        ->assertSee('design-next', false)
        ->assertSee('design-counter', false)
        ->assertSee('flow-gallery', false)
        ->assertSee('screen-grid', false)
        ->assertSee('All 2 screens, flow by flow', false);

    // The ordered walk starts at the index and leads with each flow's entry.
    $html = $response->getContent();
    $indexPosition = strpos($html, '"url":"\/larapilot\/design\/presentation"');
    $entryPosition = strpos($html, '"url":"\/mockups\/US-001"');
    $darkPosition = strpos($html, '"url":"\/mockups\/US-001\/dark.html"');

    expect($indexPosition)->not->toBeFalse()
        ->and($entryPosition)->not->toBeFalse()
        ->and($darkPosition)->not->toBeFalse()
        ->and($indexPosition)->toBeLessThan($entryPosition)
        ->and($entryPosition)->toBeLessThan($darkPosition);

    // The viewer opens on the first mockup, not on the cover sheet.
    expect($html)->toContain('<iframe id="design-frame" class="design-frame" src="/mockups/US-001"');

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

it('compares mockup style variants and records the chosen direction', function (): void {
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
        ->assertSee('Compare styles', false)
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
