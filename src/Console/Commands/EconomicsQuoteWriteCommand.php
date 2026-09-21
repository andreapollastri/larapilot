<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\EconomicsService;
use Larapilot\Support\LarapilotCommand;

class EconomicsQuoteWriteCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:economics-quote-write
                            {--file= : Path to the quote markdown file}
                            {--content= : Quote markdown content}
                            {--lang= : Document language tag (en, it, es, fr, de, pt, …)}';

    protected $description = 'Persist the client-facing commercial quote written in the PRD language';

    public function handle(ConfigService $config, EconomicsService $economics): int
    {
        if (! $config->accountEnabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'Account mode is NONE (settings.account = NONE).',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --account=FREELANCE  or  --account=COMPANY'
            );
        }

        $content = $this->option('content');

        if ($file = $this->option('file')) {
            if (! is_file($file)) {
                return $this->failure('E_NOT_FOUND', "Quote file not found: {$file}", $this->exitForCode('E_NOT_FOUND'));
            }

            $content = file_get_contents($file) ?: '';
        }

        if ($content === null || $content === '') {
            $content = stream_get_contents(STDIN) ?: '';
        }

        $content = (string) $content;

        if (trim($content) === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Quote content is empty.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Pass --file, --content, or pipe markdown via stdin.'
            );
        }

        if (preg_match('/^#\s+\S/m', $content) !== 1) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Quote content has no level-1 heading.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Start the document with the offer title, e.g. "# Offerta commerciale".'
            );
        }

        if (strlen(trim($content)) < 400) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Quote content is too short to be a client document.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Include at least the summary, what is delivered, the investment table, timeline, and payment terms.'
            );
        }

        $lang = $this->option('lang');
        $result = $economics->writeQuote($content, is_string($lang) ? $lang : null);

        return $this->success('economics_quote', $result + [
            'hint' => 'Download at /larapilot/economics/quote.md or via larapilot:economics-show --format=quote',
        ]);
    }
}
