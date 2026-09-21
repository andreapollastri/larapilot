<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\EconomicsMarketService;
use Larapilot\Services\EconomicsService;
use Larapilot\Support\LarapilotCommand;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class EconomicsMarketWriteCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:economics-market-write
                            {--file= : Path to the market research YAML file}
                            {--content= : Market research YAML content}';

    protected $description = 'Persist the researched market: competitors, price trend, demand scenarios, packaging tiers';

    public function handle(ConfigService $config, EconomicsService $economics, EconomicsMarketService $market): int
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
                return $this->failure('E_NOT_FOUND', "Market research file not found: {$file}", $this->exitForCode('E_NOT_FOUND'));
            }

            $content = file_get_contents($file) ?: '';
        }

        if ($content === null || $content === '') {
            $content = stream_get_contents(STDIN) ?: '';
        }

        if (trim((string) $content) === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Market research content is empty.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Pass --file, --content, or pipe YAML via stdin.'
            );
        }

        try {
            $parsed = Yaml::parse((string) $content);
        } catch (ParseException $exception) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Market research is not valid YAML: '.$exception->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        if (! is_array($parsed) || array_is_list($parsed)) {
            return $this->failure(
                'E_INVALID_INPUT',
                'Market research must be a YAML mapping with sector, competitors, demand, and tiers.',
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        try {
            $result = $market->write($parsed, $economics->inputsFingerprint());
        } catch (\InvalidArgumentException $exception) {
            return $this->failure('E_INVALID_INPUT', $exception->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        $economics->snapshot();

        return $this->success('economics_market', $result + [
            'hint' => 'Open /larapilot/economics — competitors, packaging, and the three business-plan lines now read from this file.',
        ]);
    }
}
