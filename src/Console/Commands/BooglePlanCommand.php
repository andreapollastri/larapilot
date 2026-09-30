<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

class BooglePlanCommand extends ErrorsPlanCommand
{
    protected $signature = 'larapilot:boogle-plan
                            {--codes= : Comma-separated error codes (#BUG12) or keys confirmed for triage}';

    protected $description = 'Group the errors the user confirmed for triage — the same as larapilot:errors-plan';
}
