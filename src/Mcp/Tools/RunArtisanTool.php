<?php

declare(strict_types=1);

namespace Larapilot\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class RunArtisanTool extends Tool
{
    protected string $description = 'Run a Larapilot Artisan command and return its JSON envelope output.';

    /**
     * @var list<string>
     */
    protected array $allowed = [
        'larapilot:config-show',
        'larapilot:spec-list',
        'larapilot:spec-show',
        'larapilot:spec-next',
        'larapilot:metrics',
        'larapilot:validate-prd',
        'larapilot:validate-spec',
        'larapilot:validate-plan',
        'larapilot:doctor',
    ];

    public function handle(Request $request): Response
    {
        $command = $request->string('command')->toString();

        if (! in_array($command, $this->allowed, true)) {
            return Response::error('Command not allowed. Use one of the Larapilot read/validate commands.');
        }

        $parameters = $request->array('parameters');

        $exitCode = Artisan::call($command, is_array($parameters) ? $parameters : []);

        return Response::json([
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()->description('Larapilot artisan command name')->required(),
            'parameters' => $schema->object()->description('Command parameters as key-value pairs'),
        ];
    }
}
