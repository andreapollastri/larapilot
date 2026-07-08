<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecApproveCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-approve {code : Spec code}';

    protected $description = 'Mark a reviewed spec as DONE after human approval';

    public function handle(SpecService $specs, ConfigService $config): int
    {
        $code = (string) $this->argument('code');
        $spec = $specs->find($code);

        if ($spec === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        if (($guard = $this->guardStatus($spec, [$config->status('review')], 'approve')) !== null) {
            return $guard;
        }

        $specs->setStatus($code, $config->status('done'));

        return $this->success('approve_result', [
            'code' => $code,
            'status' => $config->status('done'),
        ]);
    }
}
