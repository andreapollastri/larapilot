<?php

declare(strict_types=1);

use Larapilot\Services\ApiAuditService;

it('adds baseline security headers to the API and dashboard', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $api = $this->getJson('/larapilot/api/board');
    $api->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    expect($api->headers->get('X-Frame-Options'))->toBeNull();

    $this->get('/larapilot')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('serves an ETag and answers 304 on a matching If-None-Match', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $first = $this->getJson('/larapilot/api/board')->assertOk();
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->getJson('/larapilot/api/board', ['If-None-Match' => $etag])
        ->assertStatus(304);
});

it('paginates the spec list', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['code' => 'US-001']);
    addSpec(['code' => 'US-002', 'title' => 'Two']);
    addSpec(['code' => 'US-003', 'title' => 'Three']);

    $this->getJson('/larapilot/api/specs?per_page=2&page=1')
        ->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonPath('per_page', 2)
        ->assertJsonPath('page', 1)
        ->assertJsonPath('total_pages', 2)
        ->assertJsonPath('count', 2)
        ->assertJsonCount(2, 'items');

    $this->getJson('/larapilot/api/specs?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('page', 2)
        ->assertJsonPath('count', 1)
        ->assertJsonCount(1, 'items');

    // Out-of-range page clamps to the last page rather than erroring.
    $this->getJson('/larapilot/api/specs?per_page=2&page=99')
        ->assertOk()
        ->assertJsonPath('page', 2);
});

it('rate-limits the API per IP when configured', function (): void {
    config()->set('larapilot.api.rate_limit', '2,1');

    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->getJson('/larapilot/api/board')->assertOk();
    $this->getJson('/larapilot/api/board')->assertOk();
    $this->getJson('/larapilot/api/board')->assertStatus(429);
});

it('does not rate-limit when the limit is disabled', function (): void {
    config()->set('larapilot.api.rate_limit', '0');

    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    foreach (range(1, 5) as $i) {
        $this->getJson('/larapilot/api/board')->assertOk();
    }
});

it('writes an audit line for mutating API requests only', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'PLANNED']);

    $this->getJson('/larapilot/api/board')->assertOk();

    $audit = app(ApiAuditService::class);
    expect($audit->entries())->toBe([]);

    $this->postJson('/larapilot/api/specs/US-001/comments', [
        'author' => 'CI',
        'message' => 'Nightly check.',
    ])->assertCreated();

    $entries = $audit->entries();
    expect($entries)->toHaveCount(1)
        ->and($entries[0]['method'])->toBe('POST')
        ->and($entries[0]['path'])->toBe('/larapilot/api/specs/US-001/comments')
        ->and($entries[0]['status'])->toBe(201)
        ->and($entries[0]['token'])->toBeFalse();

    expect(file_get_contents(base_path('.gitignore')))->toContain('/.larapilot/api-audit.log');
});

it('can disable the API audit log', function (): void {
    config()->set('larapilot.api.audit', false);

    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'PLANNED']);

    $this->postJson('/larapilot/api/specs/US-001/comments', [
        'author' => 'CI',
        'message' => 'No audit.',
    ])->assertCreated();

    expect(is_file(app(ApiAuditService::class)->path()))->toBeFalse();
});

it('serves the delivery metrics endpoint', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    $this->getJson('/larapilot/api/metrics')
        ->assertOk()
        ->assertJsonStructure([
            'collected_at',
            'backlog' => ['total', 'done', 'by_status'],
            'plan',
            'delivery',
        ]);
});
