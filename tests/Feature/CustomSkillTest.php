<?php

declare(strict_types=1);

use Larapilot\Services\ConfigService;
use Larapilot\Services\CustomSkillService;

it('creates skills gitkeep on install', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    expect(base_path('.larapilot/skills/.gitkeep'))->toBeFile();
});

it('creates gitkeep when ensuring the custom skills directory', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $skills = app(CustomSkillService::class);
    $dir = $skills->directory();

    if (is_dir($dir)) {
        @unlink($dir.'/.gitkeep');
    }

    $skills->ensureDirectory();

    expect($dir.'/.gitkeep')->toBeFile();
});

it('persists a custom skill under .larapilot/skills and registers it with Boost', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $draft = base_path('.larapilot/tmp-deploy-checklist-SKILL.md');
    file_put_contents($draft, <<<'MD'
---
name: deploy-checklist
description: Run the team deploy checklist before a production ship.
---

# Deploy checklist

Walk Jack through the pre-ship gate.
MD);

    $this->artisan('larapilot:custom-skill-add', [
        '--name' => 'deploy-checklist',
        '--file' => $draft,
    ])->assertSuccessful();

    expect(base_path('.larapilot/skills/deploy-checklist/SKILL.md'))->toBeFile()
        ->and(base_path('.larapilot/skills/.gitkeep'))->toBeFile()
        ->and(base_path('.ai/skills/deploy-checklist/SKILL.md'))->toBeFile()
        ->and(file_get_contents(base_path('.ai/skills/deploy-checklist/SKILL.md')))
        ->toContain('Run the team deploy checklist');
});

it('refuses to overwrite a custom skill without force', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();

    $content = "---\nname: once\ndescription: First version.\n---\n\n# Once\n";

    $this->artisan('larapilot:custom-skill-add', [
        '--name' => 'once',
        '--content' => $content,
    ])->assertSuccessful();

    $this->artisan('larapilot:custom-skill-add', [
        '--name' => 'once',
        '--content' => $content,
    ])
        ->assertExitCode(2)
        ->expectsOutputToContain('E_INVALID_INPUT');

    $this->artisan('larapilot:custom-skill-add', [
        '--name' => 'once',
        '--content' => "---\nname: once\ndescription: Second version.\n---\n\n# Once\n",
        '--force' => true,
    ])->assertSuccessful();

    expect(file_get_contents(base_path('.larapilot/skills/once/SKILL.md')))
        ->toContain('Second version.');
});

it('auto-registers skills already present when listing', function (): void {
    $config = app(ConfigService::class);
    $config->writeProjectConfig();

    $dir = $config->absolutePath('.larapilot/skills/demo/');
    mkdir($dir, 0755, true);
    file_put_contents($dir.'SKILL.md', "---\nname: demo-skill\ndescription: Demo checklist for staging.\n---\n\n# Demo\n");

    $this->artisan('larapilot:custom-skill-list')->assertSuccessful();

    expect(base_path('.larapilot/skills/.gitkeep'))->toBeFile()
        ->and(base_path('.ai/skills/demo/SKILL.md'))->toBeFile()
        ->and(app(CustomSkillService::class)->list()[0]['name'] ?? null)->toBe('demo-skill')
        ->and(app(CustomSkillService::class)->list()[0]['registered'] ?? false)->toBeTrue();
});
