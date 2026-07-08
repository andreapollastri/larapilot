<?php

declare(strict_types=1);

namespace Larapilot\Services;

class PrdService
{
    public function __construct(
        protected ConfigService $config,
    ) {}

    public function path(): string
    {
        $config = $this->config->resolve();

        return $this->config->absolutePath($config['paths']['prd'] ?? '.larapilot/docs/PRD.md');
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function read(): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        return file_get_contents($this->path()) ?: null;
    }

    public function write(string $content): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $content);
    }
}
