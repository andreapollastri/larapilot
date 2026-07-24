<?php

declare(strict_types=1);

namespace Larapilot\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Larapilot\Services\DiagnosticsService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class DiagnosticsTool extends Tool
{
    protected string $description = 'Collect a read-only Larapilot diagnostics snapshot for bug triage: app status, health checks (storage, cache, database, queue, log file), and an optional redacted Laravel log tail. Never exposes secrets; use during /larapilot-bug intake.';

    public function __construct(protected DiagnosticsService $diagnostics) {}

    public function handle(Request $request): Response
    {
        $linesRaw = $request->get('lines');
        $lines = is_numeric($linesRaw) ? (int) $linesRaw : null;
        $includeLogs = $request->has('include_logs')
            ? (bool) $request->get('include_logs')
            : true;

        return Response::json($this->diagnostics->snapshot($lines, $includeLogs));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lines' => $schema->integer()->description('Max log lines to return (capped by config; default 100)'),
            'include_logs' => $schema->boolean()->description('Include redacted log tail (default true)'),
        ];
    }
}
