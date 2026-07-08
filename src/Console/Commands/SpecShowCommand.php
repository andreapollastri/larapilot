<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecShowCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-show {code : Spec code, e.g. US-001}';

    protected $description = 'Show one spec and its tasks';

    public function handle(SpecService $specs): int
    {
        $code = (string) $this->argument('code');
        $data = $specs->show($code);

        if ($data === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        return $this->success('spec_detail', $data);
    }
}
