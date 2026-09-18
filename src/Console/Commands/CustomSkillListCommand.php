<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\CustomSkillService;
use Larapilot\Support\LarapilotCommand;

class CustomSkillListCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:custom-skill-list';

    protected $description = 'List custom Larapilot skills under .larapilot/skills/ and register them with Boost';

    public function handle(CustomSkillService $customSkills): int
    {
        $registered = $customSkills->registerAll();
        $skills = $customSkills->list();

        return $this->success('custom_skill_list', [
            'skills' => $skills,
            'count' => count($skills),
            'directory' => $customSkills->directory(),
            'registered' => $registered,
        ]);
    }
}
