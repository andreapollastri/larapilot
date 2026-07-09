<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Illuminate\Support\Facades\File;

final class SharedRuntime
{
    /**
     * Packaged docs copied into `.larapilot/` on install/update.
     *
     * @return array<string, string> package filename => project filename
     */
    public static function packagedDocs(): array
    {
        return [
            'shared-runtime.md' => 'shared-runtime.md',
            'task-templates.md' => 'task-templates.md',
        ];
    }

    public static function packagePath(): string
    {
        return self::packageDocPath('shared-runtime.md');
    }

    public static function projectPath(): string
    {
        return self::projectDocPath('shared-runtime.md');
    }

    public static function packageDocPath(string $filename): string
    {
        return dirname(__DIR__, 2).'/resources/larapilot/'.$filename;
    }

    public static function projectDocPath(string $filename): string
    {
        return base_path('.larapilot/'.$filename);
    }

    /**
     * Copy packaged docs into the project. They ship with the package and
     * carry no project-specific customization, so a package upgrade can
     * always overwrite them — unlike config.yaml.
     */
    public static function refresh(): void
    {
        foreach (self::packagedDocs() as $packageFile => $projectFile) {
            AtomicFile::write(
                self::projectDocPath($projectFile),
                File::get(self::packageDocPath($packageFile))
            );
        }
    }
}
