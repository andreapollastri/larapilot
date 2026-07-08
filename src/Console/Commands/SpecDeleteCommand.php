<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SpecService;
use Larapilot\Support\LarapilotCommand;

class SpecDeleteCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:spec-delete {code : Spec code}';

    protected $description = 'Remove a spec from the backlog together with its spec and plan files';

    public function handle(SpecService $specs): int
    {
        $code = (string) $this->argument('code');

        if ($specs->find($code) === null) {
            return $this->failure('E_NOT_FOUND', "Spec {$code} not found.", $this->exitForCode('E_NOT_FOUND'));
        }

        $specs->delete($code);

        return $this->success('spec_delete_result', [
            'code' => $code,
            'summary' => $specs->list()['summary'],
        ]);
    }
}
