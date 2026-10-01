<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Larapilot\Mcp\LarapilotServer;
use Larapilot\Mcp\Tools\RunArtisanTool;

/**
 * @return array<string, mixed>
 */
function runArtisanToolDefaults(): array
{
    return (new ReflectionClass(RunArtisanTool::class))->getDefaultProperties();
}

/**
 * `backstage-export --write` lands in the project root, which under
 * Testbench is the shared skeleton app — clean it around every test.
 */
function clearMcpArtifacts(): void
{
    foreach ([base_path('catalog-info.yaml'), base_path('mkdocs.yml'), base_path('bundle.json'), base_path('usage.md')] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

beforeEach(fn () => clearMcpArtifacts());
afterEach(fn () => clearMcpArtifacts());

it('refuses a command that is not a read or a validation', function (): void {
    LarapilotServer::tool(RunArtisanTool::class, ['command' => 'larapilot:spec-approve', 'parameters' => ['code' => 'US-001']])
        ->assertHasErrors(['Command not allowed']);
});

it('runs a read command with the parameters that only read', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    LarapilotServer::tool(RunArtisanTool::class, ['command' => 'larapilot:spec-show', 'parameters' => ['code' => 'US-001', '--fields' => 'title']])
        ->assertHasNoErrors()
        ->assertSee('US-001');

    LarapilotServer::tool(RunArtisanTool::class, ['command' => 'larapilot:spec-list', 'parameters' => ['--status' => 'TODO']])
        ->assertHasNoErrors()
        ->assertSee('US-001');

    LarapilotServer::tool(RunArtisanTool::class, ['command' => 'larapilot:backstage-export'])
        ->assertHasNoErrors()
        ->assertSee('backstage-export');
});

it('tells the client it only reads, and which commands it runs', function (): void {
    $tool = app(RunArtisanTool::class)->toArray();

    expect($tool['annotations']['readOnlyHint'] ?? null)->toBeTrue()
        ->and($tool['inputSchema']['properties']['command']['enum'] ?? null)->toBe(runArtisanToolDefaults()['allowed']);
});

it('gives the command what a terminal would give it', function (): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    // A number is a string, a flag set to false is a flag left out.
    LarapilotServer::tool(RunArtisanTool::class, ['command' => 'larapilot:metrics', 'parameters' => ['--human' => false]])
        ->assertHasNoErrors()
        ->assertSee('total_points')
        ->assertDontSee('+---');

    LarapilotServer::tool(RunArtisanTool::class, ['command' => 'larapilot:metrics', 'parameters' => ['--human' => true]])
        ->assertHasNoErrors()
        ->assertSee('+---');

    LarapilotServer::tool(RunArtisanTool::class, ['command' => 'larapilot:code-history', 'parameters' => ['--limit' => 5, '--spec' => null]])
        ->assertHasNoErrors()
        ->assertSee('code_history');
});

it('refuses a value of a type the command never sees', function (string $command, array $parameters, string $mistyped): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    // Refused by the tool: not thrown by the command, with a path in the message.
    LarapilotServer::tool(RunArtisanTool::class, ['command' => $command, 'parameters' => $parameters])
        ->assertHasErrors(['Parameter value not accepted: '.$mistyped])
        ->assertDontSee('.php');
})->with([
    'a list where a value goes' => ['larapilot:spec-list', ['--status' => ['TODO', 'DONE']], '--status takes a string or a number'],
    'a boolean where a value goes' => ['larapilot:spec-list', ['--status' => true], '--status takes a string or a number'],
    'an object for an argument' => ['larapilot:spec-show', ['code' => ['a' => 'b']], 'code takes a string or a number'],
    'a string for a flag' => ['larapilot:metrics', ['--human' => 'false'], '--human takes true or false'],
    'a string for --verbose' => ['larapilot:quality', ['--verbose' => 'yes'], '--verbose takes true or false'],
]);

it('refuses the options that write a file, and writes nothing', function (string $command, array $parameters, string $refused): void {
    $this->artisan('larapilot:install')->assertSuccessful();
    addSpec();

    LarapilotServer::tool(RunArtisanTool::class, ['command' => $command, 'parameters' => $parameters])
        ->assertHasErrors(['Parameter not allowed through MCP: '.$refused]);

    expect(base_path('catalog-info.yaml'))->not->toBeFile()
        ->and(base_path('mkdocs.yml'))->not->toBeFile()
        ->and(base_path('bundle.json'))->not->toBeFile()
        ->and(base_path('usage.md'))->not->toBeFile();
})->with([
    'quality --fix' => ['larapilot:quality', ['--fix' => true], '--fix'],
    'backstage-export --write' => ['larapilot:backstage-export', ['--write' => true], '--write'],
    'backstage-export --write --force' => ['larapilot:backstage-export', ['--write' => true, '--force' => true], '--write, --force'],
    'backstage-export --file' => ['larapilot:backstage-export', ['--file' => 'bundle.json'], '--file'],
    'backstage-export --catalog' => ['larapilot:backstage-export', ['--api-base' => 'https://app.test/larapilot/api', '--catalog' => 'catalog-info.yaml'], '--catalog'],
    'usage-report --output' => ['larapilot:usage-report', ['--output' => 'usage.md'], '--output'],
    'aikido-issues --report' => ['larapilot:aikido-issues', ['--report' => true], '--report'],
    'aikido-repos --use' => ['larapilot:aikido-repos', ['--use' => '12'], '--use'],
    'errors-list --report' => ['larapilot:errors-list', ['--new' => true, '--report' => true], '--report'],
    'boogle-errors --report' => ['larapilot:boogle-errors', ['--new' => true, '--report' => true], '--report'],
    'an option set to false' => ['larapilot:quality', ['--fix' => false], '--fix'],
    'a parameter of a command that takes none' => ['larapilot:github-status', ['--fix' => true], '--fix'],
    'a list in place of key-value pairs' => ['larapilot:quality', ['--fix'], '0'],
    'a second command' => ['larapilot:spec-show', ['code' => 'US-001', 'command' => 'larapilot:spec-approve'], 'command'],
]);

it('names only parameters the commands have', function (): void {
    $defaults = runArtisanToolDefaults();
    $commands = Artisan::all();

    expect(array_diff(array_keys($defaults['parameters']), $defaults['allowed']))->toBe([]);

    foreach ($defaults['parameters'] as $command => $parameters) {
        $definition = $commands[$command]->getDefinition();

        foreach ($parameters as $parameter) {
            $known = match (true) {
                $parameter === '--verbose' => true,
                str_starts_with($parameter, '--') => $definition->hasOption(substr($parameter, 2)),
                default => $definition->hasArgument($parameter),
            };

            expect($known)->toBeTrue("{$command} has no {$parameter}");
        }
    }
});

it('leaves out every option that writes, and nothing else', function (): void {
    $defaults = runArtisanToolDefaults();
    $commands = Artisan::all();
    $closed = [];

    foreach ($defaults['allowed'] as $command) {
        $definition = $commands[$command]->getDefinition();
        $has = [
            ...array_keys($definition->getArguments()),
            ...array_map(fn (string $option): string => '--'.$option, array_keys($definition->getOptions())),
        ];

        if (($left = array_values(array_diff($has, $defaults['parameters'][$command] ?? []))) !== []) {
            $closed[$command] = $left;
        }
    }

    // An option added to one of these commands fails here until it is
    // named in the tool (it only reads) or in this list (it writes).
    expect($closed)->toBe([
        'larapilot:usage-report' => ['--output'],
        'larapilot:aikido-issues' => ['--report'],
        'larapilot:aikido-repos' => ['--use', '--forget'],
        'larapilot:errors-list' => ['--report'],
        'larapilot:boogle-errors' => ['--report'],
        'larapilot:quality' => ['--fix'],
        'larapilot:backstage-export' => ['--write', '--force', '--catalog', '--mkdocs', '--no-techdocs', '--file'],
        'larapilot:upgrade-check' => ['--report'],
        'larapilot:sbom' => ['--write'],
    ]);
});
