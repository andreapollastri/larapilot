<?php

declare(strict_types=1);

namespace Larapilot\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larapilot\Services\SpecService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SpecShowTool extends Tool
{
    protected string $description = 'Show a Larapilot spec by code (e.g. US-001) including its implementation plan tasks and working directory.';

    public function __construct(protected SpecService $specs) {}

    public function handle(Request $request): Response
    {
        $code = $request->string('code')->toString();

        if ($code === '') {
            return Response::error('Spec code is required.');
        }

        $data = $this->specs->show($code);

        if ($data === null) {
            return Response::error("Spec {$code} not found.");
        }

        return Response::json($data);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Spec code, e.g. US-001')->required(),
        ];
    }
}
