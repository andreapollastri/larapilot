<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Illuminate\Support\Facades\File;

final class SharedRuntime
{
    public static function packagePath(): string
    {
        return dirname(__DIR__, 2).'/resources/larapilot/shared-runtime.md';
    }

    public static function projectPath(): string
    {
        return base_path('.larapilot/shared-runtime.md');
    }

    /**
     * Copy the packaged runtime doc into the project. It ships with the
     * package and carries no project-specific customization, so a package
     * upgrade can always overwrite it — unlike config.yaml.
     */
    public static function refresh(): void
    {
        AtomicFile::write(self::projectPath(), File::get(self::packagePath()));
    }
}
