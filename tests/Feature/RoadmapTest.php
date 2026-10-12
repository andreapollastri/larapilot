<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Services\PrdService;
use Larapilot\Services\ReleaseService;
use Larapilot\Services\RoadmapService;

it('shows the roadmap between Plan and Design, and keeps the public link off', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $html = $this->get('/larapilot/roadmap')
        ->assertOk()
        ->assertSee('>Roadmap</a>', false)
        ->assertSee('No release in the ledger yet', false)
        ->assertSee('The public link is off.', false)
        ->getContent();

    expect(strpos($html, '>Plan</a>'))->toBeLessThan(strpos($html, '>Roadmap</a>'))
        ->and(strpos($html, '>Roadmap</a>'))->toBeLessThan(strpos($html, '>Design</a>'));

    $this->get('/larapilot/roadmap/p/aaaaaaaaaaaaaaaaaaaa')->assertNotFound();
});

it('publishes titles and versions, and a new link retires the old one', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'YES'])->assertSuccessful();
    app(ReleaseService::class)->add('1.0.0', 'Initial release', 'planned', ['US-001']);
    $this->artisan('larapilot:settings-set', ['--public-roadmap' => 'YES'])->assertSuccessful();

    $roadmap = app(RoadmapService::class);
    $roadmap->ensureToken();
    $url = (string) $roadmap->publicUrl();

    $this->get('/larapilot/roadmap')
        ->assertOk()
        ->assertSee($url, false)
        ->assertSee('v1.0.0', false)
        ->assertSee('Initial release', false)
        ->assertSee('Login', false);

    $public = $this->get($url)
        ->assertOk()
        ->assertSee('v1.0.0', false)
        ->assertSee('Initial release', false)
        ->assertSee('Login', false)
        ->assertDontSee('US-001', false)
        ->assertDontSee('New link', false);

    expect($public->getContent())->not->toContain('hours')
        ->and($public->getContent())->not->toContain('git');

    $this->get('/larapilot/roadmap/p/bbbbbbbbbbbbbbbbbbbb')->assertNotFound();

    dashboardPost('/larapilot/roadmap/regenerate')->assertRedirect('/larapilot/roadmap');

    $this->get($url)->assertNotFound();

    $next = (string) app(RoadmapService::class)->publicUrl();

    expect($next)->not->toBe($url);

    $this->get($next)->assertOk()->assertSee('Initial release', false)->assertDontSee('US-001', false);

    $this->artisan('larapilot:settings-set', ['--public-roadmap' => 'NO'])->assertSuccessful();

    $this->get($next)->assertNotFound();
    $this->get('/larapilot/roadmap')->assertOk()->assertSee('The public link is off.', false);
});

it('answers the public link in production, where the dashboard is hidden, and keeps the token out of Git', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['title' => 'Sign in with a passkey']);
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'YES', '--public-roadmap' => 'YES'])->assertSuccessful();
    app(ReleaseService::class)->add('2.0.0', 'Passkeys', 'planned', ['US-001']);

    $roadmap = app(RoadmapService::class);
    $token = $roadmap->ensureToken();

    expect(strlen($token))->toBe(40)
        ->and(base_path('.larapilot/roadmap-link.yaml'))->toBeFile()
        ->and((string) file_get_contents(base_path('.gitignore')))->toContain('/.larapilot/roadmap-link.yaml')
        ->and(is_file($roadmap->path()) ? (string) file_get_contents($roadmap->path()) : '')->not->toContain($token);

    $url = (string) $roadmap->publicUrl();
    $this->app['env'] = 'production';

    $this->get('/larapilot')->assertNotFound();
    $this->get('/larapilot/roadmap')->assertNotFound();
    $this->get($url)->assertOk()->assertSee('Passkeys', false)->assertSee('Sign in with a passkey', false)->assertDontSee('US-001', false);
});

it('curates the public wording from the shell and says whether a link exists', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();
    $this->artisan('larapilot:settings-set', ['--release-mode' => 'YES'])->assertSuccessful();
    app(ReleaseService::class)->add('1.0.0', 'Initial release', 'planned', ['US-001']);

    Artisan::call('larapilot:roadmap-set', ['--semver' => '1.0.0', '--title' => 'First light', '--summary' => 'Sign in and get started.', '--target' => '2026-Q4']);
    Artisan::call('larapilot:roadmap-set', ['--semver' => '1.0.0', '--item' => 'US-001', '--title' => 'Sign in safely']);

    Artisan::call('larapilot:roadmap-show');
    $data = json_decode(Artisan::output(), true)['data'];
    $version = $data['versions'][0];

    expect($version['title'])->toBe('First light')
        ->and($version['summary'])->toBe('Sign in and get started.')
        ->and($version['groups'][0]['items'][0]['title'])->toBe('Sign in safely')
        ->and($data['public'])->toBe(['enabled' => false, 'url' => null, 'has_token' => false]);

    $this->artisan('larapilot:settings-set', ['--public-roadmap' => 'YES'])->assertSuccessful();
    app(RoadmapService::class)->ensureToken();

    Artisan::call('larapilot:roadmap-show');
    $public = json_decode(Artisan::output(), true)['data']['public'];

    expect($public['enabled'])->toBeTrue()
        ->and($public['has_token'])->toBeTrue()
        ->and($public['url'])->toContain('/larapilot/roadmap/p/');

    Artisan::call('larapilot:roadmap-set', ['--semver' => '1.0.0', '--hide' => true]);
    Artisan::call('larapilot:roadmap-show');

    expect(json_decode(Artisan::output(), true)['data']['versions'])->toBe([]);
});

it('names the product from the PRD heading, not from the kind of document', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    config()->set('app.name', 'Fernway App');
    $prd = app(PrdService::class);
    $roadmap = app(RoadmapService::class);

    $prd->write("# Fernway — Product Requirements Document\n\n## Elevator Pitch\n\nBook a class.\n");
    expect($roadmap->productName())->toBe('Fernway');

    $prd->write("# **Fernway**: PRD\n\n## Vision\n\nCalm gyms.\n");
    expect($roadmap->productName())->toBe('Fernway');

    $prd->write("# Product Requirements Document\n\n## Vision\n\nCalm gyms.\n");
    expect($roadmap->productName())->toBe('Fernway App');
});
