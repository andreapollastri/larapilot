<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\UsageService;
use Larapilot\Support\LarapilotCommand;

class UsageLogCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:usage-log
                            {--category=other : analysis|planning|implementation|support|feature|review|ship|other}
                            {--tokens=0 : Token count for this session slice}
                            {--minutes=0 : Wall-clock minutes}
                            {--skill= : Larapilot skill name}
                            {--spec= : Optional US-XXX}
                            {--user= : Override actor (defaults to git user)}
                            {--note= : Short note}
                            {--estimated : Mark tokens/minutes as estimates}
                            {--ts= : ISO timestamp override}';

    protected $description = 'Append a Lucille usage ledger entry (tokens + time)';

    public function handle(ConfigService $config, UsageService $usage): int
    {
        if (! $config->lucilleEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Lucille is explicitly excluded (settings.lucille = NO).',
                $this->exitForCode('E_PRECONDITION'),
                'Re-enable with: php artisan larapilot:settings-set --lucille=YES'
            );
        }

        try {
            $entry = $usage->log([
                'category' => $this->option('category'),
                'tokens' => $this->option('tokens'),
                'minutes' => $this->option('minutes'),
                'skill' => $this->option('skill'),
                'spec' => $this->option('spec'),
                'user' => $this->option('user') ?: null,
                'note' => $this->option('note'),
                'estimated' => (bool) $this->option('estimated'),
                'ts' => $this->option('ts'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        return $this->success('usage_entry', [
            'entry' => $entry,
            'ledger' => $usage->ledgerPath(),
        ]);
    }
}
