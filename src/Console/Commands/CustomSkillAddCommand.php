<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use InvalidArgumentException;
use Larapilot\Services\CustomSkillService;
use Larapilot\Support\LarapilotCommand;

class CustomSkillAddCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:custom-skill-add
                            {--name= : Kebab-case skill name (folder + slash command)}
                            {--file= : Path to SKILL.md}
                            {--content= : SKILL.md markdown content}
                            {--force : Overwrite an existing skill with the same name}';

    protected $description = 'Persist a custom Boost skill under .larapilot/skills/ and register it with Boost';

    public function handle(CustomSkillService $customSkills): int
    {
        $content = $this->option('content');

        $file = $this->option('file');

        if (is_string($file) && $file !== '') {
            if (! is_file($file)) {
                return $this->failure('E_NOT_FOUND', "Skill file not found: {$file}", $this->exitForCode('E_NOT_FOUND'));
            }

            $content = file_get_contents($file) ?: '';
        }

        if ($content === null || $content === '') {
            $content = stream_get_contents(STDIN) ?: '';
        }

        if (trim((string) $content) === '') {
            return $this->failure(
                'E_INVALID_INPUT',
                'Skill content is empty.',
                $this->exitForCode('E_INVALID_INPUT'),
                'Pass --file, --content, or pipe SKILL.md via stdin.'
            );
        }

        try {
            $saved = $customSkills->write(
                (string) ($this->option('name') ?? ''),
                (string) $content,
                (bool) $this->option('force')
            );
        } catch (InvalidArgumentException $e) {
            return $this->failure(
                'E_INVALID_INPUT',
                $e->getMessage(),
                $this->exitForCode('E_INVALID_INPUT')
            );
        }

        $published = $this->publishBoost();

        return $this->success('custom_skill', [
            'skill' => $saved,
            'boost_published' => $published,
        ]);
    }

    protected function publishBoost(): bool
    {
        if ($this->laravel->runningUnitTests()) {
            return false;
        }

        if ($this->getApplication()?->has('boost:update') !== true) {
            return false;
        }

        try {
            return $this->call('boost:update') === self::SUCCESS;
        } catch (\Throwable) {
            return false;
        }
    }
}
