<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecStartCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-start {code : Spec code}';

    protected $description = 'Move a planned spec to IN PROGRESS';

    public function handle(SpecService $specs, ConfigService $config, string $code): int
    {
        $spec = $specs->find($code);

        if ($spec === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        $specs->setStatus($code, $config->status('in_progress'));

        return $this->success('status_result', [
            'code' => $code,
            'status' => $config->status('in_progress'),
        ]);
    }
}
