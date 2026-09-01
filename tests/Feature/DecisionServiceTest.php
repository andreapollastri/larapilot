<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\DecisionService;

it('appends a decision with an id, timestamp and normalized topic', function (): void {
    $decisions = app(DecisionService::class);

    $entry = $decisions->log([
        'topic' => '  Background   Color ',
        'label' => 'Background color',
        'value' => 'orange',
        'source' => 'askquestion',
        'skill' => 'larapilot-inception',
    ]);

    expect($entry['id'])->toHaveLength(16)
        ->and($entry['ts'])->not->toBeEmpty()
        ->and($entry['topic'])->toBe('background color')
        ->and($entry['label'])->toBe('Background color')
        ->and($entry['value'])->toBe('orange')
        ->and($entry['source'])->toBe('askquestion')
        ->and($entry['phase'])->toBe('larapilot-inception')
        ->and($decisions->entries())->toHaveCount(1);
});

it('rejects a decision without a topic or value', function (): void {
    $decisions = app(DecisionService::class);

    expect(fn () => $decisions->log(['value' => 'orange']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $decisions->log(['topic' => 'background color']))
        ->toThrow(InvalidArgumentException::class);
});

it('flags a differing later value on the same topic as a conflict', function (): void {
    $decisions = app(DecisionService::class);

    $decisions->log(['topic' => 'background color', 'value' => 'orange']);

    $conflicts = $decisions->conflicts('Background Color', 'red');

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]['value'])->toBe('orange');

    expect($decisions->conflicts('background color', 'orange'))->toBeEmpty();
});

it('stops flagging a decision once it has been superseded', function (): void {
    $decisions = app(DecisionService::class);

    $orange = $decisions->log(['topic' => 'background color', 'value' => 'orange']);
    $decisions->log([
        'topic' => 'background color',
        'value' => 'red',
        'supersedes' => $orange['id'],
    ]);

    expect($decisions->conflicts('background color', 'green'))->toHaveCount(1)
        ->and($decisions->conflicts('background color', 'green')[0]['value'])->toBe('red');
});

it('matches topic history case-insensitively and by substring', function (): void {
    $decisions = app(DecisionService::class);

    $decisions->log(['topic' => 'primary background color', 'value' => 'orange']);

    expect($decisions->history('BACKGROUND color'))->toHaveCount(1);
});

it('reports regressions in the dashboard view', function (): void {
    $decisions = app(DecisionService::class);

    $decisions->log(['topic' => 'background color', 'value' => 'orange', 'label' => 'Background color']);
    $decisions->log(['topic' => 'background color', 'value' => 'red', 'label' => 'Background color']);
    $decisions->log(['topic' => 'framework', 'value' => 'Laravel']);

    $dashboard = $decisions->dashboard();

    expect($dashboard['entry_count'])->toBe(3)
        ->and($dashboard['regressions'])->toHaveCount(1)
        ->and($dashboard['regressions'][0]['topic'])->toBe('background color')
        ->and($dashboard['regressions'][0]['current_value'])->toBe('red');
});

it('exposes decision_log defaults through settings and honors the toggle', function (): void {
    $config = app(ConfigService::class);

    expect($config->decisionLogEnabled())->toBeTrue();

    $config->updateSettings(['decision_log' => 'NO']);

    expect($config->decisionLogEnabled())->toBeFalse();
});
