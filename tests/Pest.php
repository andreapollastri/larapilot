<?php

declare(strict_types=1);

use Larapilot\Tests\DisabledTestCase;
use Larapilot\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class)->in('Feature');
uses(DisabledTestCase::class)->in('Disabled');

function validSpecBody(): string
{
    return <<<'MD'
**User Story**
As a user,
I want to log in,
so that I can access my account.

**Demonstrates**
After implementing this spec, login works end to end.

**Acceptance Criteria**
- [ ] Happy path
- [ ] Error case
MD;
}

function validPrd(): string
{
    return <<<'MD'
# Product

## Elevator Pitch
A thing.

## Vision
Make it great.

## User Personas
Developers.

## Functional Requirements
- Login

## MVP Scope
Login only.

## Technical Architecture
Laravel monolith.
MD;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function specsPayload(array $overrides = []): array
{
    return [
        'specs' => [
            array_merge([
                'code' => 'US-001',
                'title' => 'Login',
                'priority' => 'HIGH',
                'points' => 3,
                'status' => 'TODO',
                'body' => validSpecBody(),
            ], $overrides),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function planPayload(): array
{
    return [
        'plan_body' => 'Technical solution and test strategy.',
        'tasks' => [
            [
                'id' => 'TASK-01',
                'title' => 'Create model',
                'type' => 'implementation',
                'status' => 'TODO',
                'body' => "## Description\nCreate the model.",
            ],
            [
                'id' => 'TASK-02',
                'title' => 'Write tests',
                'type' => 'test',
                'status' => 'TODO',
                'body' => "## Description\nWrite the tests.",
                'dependencies' => ['TASK-01'],
            ],
        ],
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function payloadFile(array $payload, string $name = 'tmp-payload.yaml'): string
{
    $path = base_path('.larapilot/'.$name);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, Yaml::dump($payload, 4, 2));

    return $path;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function addSpec(array $overrides = []): void
{
    test()->artisan('larapilot:spec-add', ['--file' => payloadFile(specsPayload($overrides))])
        ->assertSuccessful();
}

function planSpec(string $code = 'US-001'): void
{
    test()->artisan('larapilot:spec-plan', ['code' => $code, '--file' => payloadFile(planPayload(), 'tmp-plan.yaml')])
        ->assertSuccessful();
}
