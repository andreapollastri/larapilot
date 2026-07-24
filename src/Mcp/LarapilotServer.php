<?php

declare(strict_types=1);

namespace Larapilot\Mcp;

use Larapilot\Mcp\Tools\BacklogListTool;
use Larapilot\Mcp\Tools\DiagnosticsTool;
use Larapilot\Mcp\Tools\RunArtisanTool;
use Larapilot\Mcp\Tools\SpecShowTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

class LarapilotServer extends Server
{
    protected string $name = 'Larapilot';

    protected string $version = '2.0.0';

    protected string $instructions = 'Spec-driven product workflow for Laravel. Manage PRD, backlog, specs, plans, and workflow status. Use diagnostics for bug triage (status + redacted logs). Use alongside Laravel Boost for implementation.';

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        BacklogListTool::class,
        SpecShowTool::class,
        DiagnosticsTool::class,
        RunArtisanTool::class,
    ];
}
