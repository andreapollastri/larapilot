<?php

declare(strict_types=1);

namespace Larapilot\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Larapilot\Services\SpecService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class BacklogListTool extends Tool
{
    protected string $description = 'List Larapilot backlog specs with optional status filter. Returns spec codes, titles, priorities, and workflow status.';

    public function __construct(protected SpecService $specs) {}

    public function handle(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: null;
        $data = $this->specs->list($status);

        return Response::json($data);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Optional workflow status filter (TODO, PLANNED, etc.)'),
        ];
    }
}
