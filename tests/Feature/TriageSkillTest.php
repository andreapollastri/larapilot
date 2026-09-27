<?php

declare(strict_types=1);

it('ships a triage skill that classifies a request and hands off', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $triage = file_get_contents($root.'/boost/skills/larapilot-triage/SKILL.md');

    expect($triage)->toStartWith("---\nname: larapilot-triage\n")
        ->and($triage)->toContain('## The promise test')
        ->and($triage)->toContain('| `larapilot-bug` |')
        ->and($triage)->toContain('| `larapilot-feature` |')
        ->and($triage)->toContain('**Bug — requirement gap**')
        ->and($triage)->toContain('**Feature — change request**')
        ->and($triage)->toContain('Wording is a hint, never the verdict')
        ->and($triage)->toContain('Triage handoff')
        ->and($triage)->toContain('**in this same turn**')
        ->and($triage)->toContain('## Other exits');
});

it('keeps triage light so the branch not taken costs nothing', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $triage = file_get_contents($root.'/boost/skills/larapilot-triage/SKILL.md');
    $index = file_get_contents($root.'/larapilot/shared-runtime.md');
    $economy = file_get_contents($root.'/larapilot/runtime-core-economy.md');

    expect(strlen($triage))->toBeLessThanOrEqual(9000)
        ->and($triage)->toContain('**every-skill rows** only')
        ->and($triage)->toContain('Do not read `runtime-ops`')
        ->and($triage)->toContain('Never load the whole PRD')
        ->and($triage)->toContain('At most one AskQuestion per request')
        ->and($triage)->toContain('Do not write a spec, a PRD edit, an intake entry, a plan, or code');

    expect($index)->toContain('`larapilot-triage` uses the every-skill rows only')
        ->and($economy)->toContain('**`larapilot-triage`**');
});

it('lets bug and feature take a triage handoff without asking again', function (): void {
    $root = dirname(__DIR__, 2).'/resources';
    $bug = file_get_contents($root.'/boost/skills/larapilot-bug/SKILL.md');
    $feature = file_get_contents($root.'/boost/skills/larapilot-feature/SKILL.md');
    $guideline = file_get_contents($root.'/boost/guidelines/core.blade.php');

    foreach ([$bug, $feature] as $skill) {
        expect($skill)->toContain('## Handoff from `larapilot-triage`')
            ->and($skill)->toContain('do not restate it or ask for it again')
            ->and($skill)->toContain('never against a verdict the user settled');
    }

    expect($bug)->toContain('hand over to `larapilot-feature`')
        ->and($feature)->toContain('hand over to `larapilot-bug`')
        ->and($guideline)->toContain('`larapilot-triage`');
});
