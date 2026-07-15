<?php

declare(strict_types=1);

use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\SpecService;

it('appends internal feedback via artisan command', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'IN PROGRESS']);

    $this->artisan('larapilot:spec-comment', [
        'code' => 'US-001',
        '--author' => 'PM',
        '--message' => 'Please confirm Safari SSO scope.',
    ])->assertSuccessful();

    $path = base_path('.larapilot/internal-feedback/US-001.md');

    expect(is_file($path))->toBeTrue()
        ->and(file_get_contents($path))->toContain('Please confirm Safari SSO scope.')
        ->and(file_get_contents($path))->toContain('status: IN PROGRESS');
});

it('rejects comments when the feature is disabled by config', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    config()->set('larapilot.comments.enabled', false);

    $this->artisan('larapilot:spec-comment', [
        'code' => 'US-001',
        '--author' => 'PM',
        '--message' => 'Should not persist.',
    ])->assertExitCode(4);
});

it('rejects comments on done specs', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'DONE']);

    $this->artisan('larapilot:spec-comment', [
        'code' => 'US-001',
        '--author' => 'PM',
        '--message' => 'Too late.',
    ])->assertExitCode(4);
});

it('includes blocking internal feedback in request changes', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    addSpec();
    planSpec();

    $this->artisan('larapilot:spec-start', ['code' => 'US-001'])->assertSuccessful();
    $this->artisan('larapilot:spec-review', ['code' => 'US-001'])->assertSuccessful();

    $this->artisan('larapilot:spec-comment', [
        'code' => 'US-001',
        '--author' => 'PM',
        '--message' => 'Empty password still fails.',
        '--blocks-merge' => true,
    ])->assertSuccessful();

    $feedback = payloadFile(['markdown' => 'Formal review rejection.'], 'tmp-feedback.yaml');

    $this->artisan('larapilot:spec-request-changes', [
        'code' => 'US-001',
        '--file' => $feedback,
        '--include-feedback' => true,
    ])->assertSuccessful();

    $spec = app(SpecService::class)->find('US-001');

    expect($spec['status'])->toBe('TODO')
        ->and($spec['rework'])->toBeTrue()
        ->and($spec['body'])->toContain('## Rework Feedback')
        ->and($spec['body'])->toContain('Formal review rejection.')
        ->and($spec['body'])->toContain('Empty password still fails.')
        ->and($spec['status_history'])->not->toBeEmpty();
});

it('rejects empty dashboard comment submissions', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'PLANNED']);

    $this->from(route('larapilot.dashboard.spec', 'US-001'))
        ->post('/larapilot/specs/US-001/comments', [
            'author' => '',
            'message' => '',
        ])
        ->assertSessionHasErrors(['author', 'message']);
});

it('shows feedback entries in accordion on the dashboard spec page', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'REVIEW']);

    app(InternalFeedbackService::class)->append('US-001', 'Dev', 'Need API contract clarification.');
    app(InternalFeedbackService::class)->append('US-001', 'PM', 'Blocking issue.', statusAt: 'REVIEW', blocksMerge: true);

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertSee('Internal feedback')
        ->assertSee('feedback-accordion', false)
        ->assertSee('Need API contract clarification', false)
        ->assertSee('Needs rework', false)
        ->assertSee('placeholder="PM"', false);
});

it('accepts dashboard comment submissions when enabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'PLANNED']);

    $this->post('/larapilot/specs/US-001/comments', [
        'author' => 'PM',
        'message' => 'Clarified acceptance criteria in chat.',
    ])->assertRedirect(route('larapilot.dashboard.spec', 'US-001'));

    expect(file_get_contents(base_path('.larapilot/internal-feedback/US-001.md')))
        ->toContain('Clarified acceptance criteria in chat.');
});

it('hides dashboard comment form when comments are disabled', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'PLANNED']);

    config()->set('larapilot.comments.enabled', false);

    $this->get('/larapilot/specs/US-001')
        ->assertOk()
        ->assertDontSee('Internal feedback');

    $this->post('/larapilot/specs/US-001/comments', [
        'author' => 'PM',
        'message' => 'Should not work.',
    ])->assertNotFound();
});

it('exposes feedback metadata via the API', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec(['status' => 'REVIEW']);

    app(InternalFeedbackService::class)->append('US-001', 'PM', 'Blocking issue.', statusAt: 'REVIEW', blocksMerge: true);

    $this->getJson('/larapilot/api/specs/US-001')
        ->assertOk()
        ->assertJsonPath('feedback.entry_count', 1)
        ->assertJsonPath('feedback.blocking_count', 1)
        ->assertJsonPath('feedback.writable', true)
        ->assertJsonPath('feedback.entries.0.author', 'PM')
        ->assertJsonPath('feedback.entries.0.blocks_merge', true);

    $this->getJson('/larapilot/api/board')
        ->assertOk()
        ->assertJsonPath('columns.REVIEW.0.feedback.entry_count', 1)
        ->assertJsonPath('columns.REVIEW.0.feedback.entries.0.body', 'Blocking issue.');

    $this->getJson('/larapilot/api/specs')
        ->assertOk()
        ->assertJsonPath('items.0.feedback.entry_count', 1)
        ->assertJsonCount(1, 'items.0.feedback.entries');
});

it('deletes internal feedback when a spec is removed', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    app(InternalFeedbackService::class)->append('US-001', 'PM', 'Temporary note.');

    $path = base_path('.larapilot/internal-feedback/US-001.md');
    expect(is_file($path))->toBeTrue();

    $this->artisan('larapilot:spec-delete', ['code' => 'US-001'])->assertSuccessful();

    expect(is_file($path))->toBeFalse();
});

it('creates the internal feedback directory on install', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(is_dir(base_path('.larapilot/internal-feedback')))->toBeTrue()
        ->and(is_file(base_path('.larapilot/internal-feedback/README.md')))->toBeTrue();
});
