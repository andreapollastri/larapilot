<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\SecurityService;
use Larapilot\Support\SecurityCommand;
use Throwable;

class SecurityReposCommand extends SecurityCommand
{
    protected $signature = 'larapilot:security-repos
                            {--search= : Only the repositories whose name holds this}
                            {--use= : The repository that is this project, by id or name — kept for every machine}
                            {--forget : Forget the repository chosen with --use}';

    protected $description = 'List the repositories of the scanner (Aikido or Hackly), and say which one is this project';

    public function handle(SecurityService $security): int
    {
        if (($off = $this->scannerOff($security)) !== null) {
            return $off;
        }

        try {
            if ($this->option('forget')) {
                $security->forgetRepository();

                return $this->success('security_repos', ['provider' => $security->provider(), 'action' => 'forget']);
            }

            $use = trim((string) $this->option('use'));

            if ($use !== '') {
                return $this->success('security_repos', ['provider' => $security->provider(), 'action' => 'use', 'repository' => $security->useRepository($use)]);
            }

            return $this->success('security_repos', $security->repositories(trim((string) $this->option('search'))));
        } catch (Throwable $e) {
            return $this->failed($security, $e);
        }
    }
}
