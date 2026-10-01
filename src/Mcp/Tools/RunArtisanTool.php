<?php

declare(strict_types=1);

namespace Larapilot\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class RunArtisanTool extends Tool
{
    protected string $description = 'Run a Larapilot Artisan command that reads or validates, and return its JSON envelope output. The commands and the options that write are refused.';

    /**
     * @var list<string>
     */
    protected array $allowed = [
        'larapilot:context',
        'larapilot:config-show',
        'larapilot:spec-list',
        'larapilot:spec-show',
        'larapilot:spec-next',
        'larapilot:metrics',
        'larapilot:usage-report',
        'larapilot:decision-check',
        'larapilot:code-history',
        'larapilot:github-status',
        'larapilot:gitlab-status',
        'larapilot:bitbucket-status',
        'larapilot:azure-status',
        'larapilot:aikido-status',
        'larapilot:aikido-issues',
        'larapilot:aikido-plan',
        'larapilot:aikido-repos',
        'larapilot:errors-status',
        'larapilot:errors-list',
        'larapilot:errors-plan',
        'larapilot:boogle-status',
        'larapilot:boogle-errors',
        'larapilot:boogle-plan',
        'larapilot:validate-prd',
        'larapilot:prd-impact',
        'larapilot:prd-show',
        'larapilot:validate-spec',
        'larapilot:validate-plan',
        'larapilot:doctor',
        'larapilot:diagnostics',
        'larapilot:quality',
        'larapilot:frontend-scan',
        'larapilot:frontend-rules',
        'larapilot:backstage-export',
        'larapilot:tracker-status',
        'larapilot:hook-list',
        'larapilot:schedule-show',
        'larapilot:stack',
        'larapilot:upgrade-check',
        'larapilot:sbom',
        'larapilot:vendor-audit',
    ];

    /**
     * What each command takes from an agent. A parameter that is not listed
     * is refused, and so is every parameter of a command that is not here:
     * the options that write (`quality --fix`, `backstage-export --write`,
     * `usage-report --output`, `--report`, `aikido-repos --use`) stay with
     * Artisan run by hand, and an option added to a command later is closed until it is
     * named here.
     *
     * @var array<string, list<string>>
     */
    protected array $parameters = [
        // The session cache it keeps is derived and git-ignored: a read.
        'larapilot:context' => ['skill', '--session', '--fresh', '--with'],
        'larapilot:config-show' => ['--only'],
        'larapilot:spec-list' => ['--status', '--full'],
        'larapilot:spec-show' => ['code', '--task', '--fields'],
        'larapilot:spec-next' => ['--status', '--task', '--fields'],
        'larapilot:metrics' => ['--human'],
        'larapilot:usage-report' => ['--format', '--category', '--user', '--skill', '--spec', '--from', '--to', '--limit', '--insights'],
        'larapilot:decision-check' => ['--topic', '--value', '--limit'],
        'larapilot:code-history' => ['--file', '--spec', '--limit'],
        'larapilot:aikido-issues' => ['--severity', '--type', '--new', '--limit', '--gate'],
        'larapilot:aikido-plan' => ['--ids'],
        'larapilot:aikido-repos' => ['--search'],
        'larapilot:errors-list' => ['--new', '--kind', '--limit'],
        'larapilot:errors-plan' => ['--codes'],
        'larapilot:boogle-errors' => ['--new', '--kind', '--limit'],
        'larapilot:boogle-plan' => ['--codes'],
        'larapilot:validate-prd' => ['--file'],
        'larapilot:prd-impact' => ['--ids'],
        'larapilot:prd-show' => ['--ids', '--section'],
        'larapilot:validate-spec' => ['--file'],
        'larapilot:validate-plan' => ['code', '--file'],
        'larapilot:doctor' => ['--human'],
        'larapilot:diagnostics' => ['--lines', '--no-logs'],
        'larapilot:quality' => ['--verbose'],
        'larapilot:frontend-scan' => ['--path', '--project', '--full', '--no-cli', '--fresh'],
        'larapilot:frontend-rules' => ['--file', '--project', '--path'],
        'larapilot:backstage-export' => ['--api-base'],
        'larapilot:hook-list' => ['--event'],
        'larapilot:schedule-show' => ['--only'],
        'larapilot:tracker-status' => ['--ping'],
        'larapilot:stack' => ['--only', '--no-db'],
        'larapilot:upgrade-check' => ['--laravel', '--php', '--php-from', '--db', '--db-from', '--offline', '--gate'],
        'larapilot:sbom' => ['--full'],
        'larapilot:vendor-audit' => ['--cached', '--new', '--limit', '--fail-on', '--gate'],
    ];

    public function handle(Request $request): Response
    {
        $command = $request->string('command')->toString();

        if (! in_array($command, $this->allowed, true)) {
            return Response::error('Command not allowed. Use one of the Larapilot read/validate commands.');
        }

        $parameters = $request->array('parameters');
        $accepted = $this->parameters[$command] ?? [];
        $refused = array_values(array_diff(array_map('strval', array_keys($parameters)), $accepted));

        if ($refused !== []) {
            return Response::error(
                'Parameter not allowed through MCP: '.implode(', ', $refused).'. '
                .($accepted === [] ? "{$command} takes no parameters here." : "{$command} takes: ".implode(', ', $accepted).'.')
                .' The options that write files are left to Artisan run directly.'
            );
        }

        [$parameters, $mistyped] = $this->typed($command, $parameters);

        if ($mistyped !== []) {
            return Response::error('Parameter value not accepted: '.implode(', ', $mistyped).'.');
        }

        $exitCode = Artisan::call($command, $parameters);

        return Response::json([
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ]);
    }

    /**
     * What a terminal would give the command: `true` for a flag, a string
     * for anything else. A list, an object, or a boolean where a value goes
     * would reach the command as a type it never sees, and fail inside it.
     * A flag set to `false`, and a value set to `null`, are left out.
     *
     * @param  array<array-key, mixed>  $parameters
     * @return array{0: array<string, string|true>, 1: list<string>}
     */
    protected function typed(string $command, array $parameters): array
    {
        $definition = Artisan::all()[$command]->getDefinition();
        $typed = [];
        $mistyped = [];

        foreach ($parameters as $name => $value) {
            $name = (string) $name;
            $option = str_starts_with($name, '--') ? substr($name, 2) : null;
            $flag = $option !== null && ! ($definition->hasOption($option) && $definition->getOption($option)->acceptValue());

            if ($value === null || ($flag && $value === false)) {
                continue;
            }

            if ($flag && $value === true) {
                $typed[$name] = true;
            } elseif (! $flag && (is_string($value) || is_int($value) || is_float($value))) {
                $typed[$name] = (string) $value;
            } else {
                $mistyped[] = $name.($flag ? ' takes true or false' : ' takes a string or a number');
            }
        }

        return [$typed, $mistyped];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()->enum($this->allowed)->description('Larapilot artisan command name')->required(),
            'parameters' => $schema->object()->description('Command parameters as key-value pairs — arguments by name, options with their dashes ({"code": "US-001", "--fields": "title"}). Only the parameters that read are accepted; an option that writes a file is refused.'),
        ];
    }
}
