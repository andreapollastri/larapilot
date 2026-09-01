<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Support\AtomicFile;
use Larapilot\Support\LarapilotCommand;

/**
 * Emit the standalone VPS provisioning script (`provision.sh`) so it can be
 * copied to an Ubuntu 24.04/26.04 server. The script embeds and generates the
 * `prj-ai` / `prj-work` / `prj-pr` CLIs for hosting multiple Laravel projects
 * driven by Claude Code + Larapilot, with GitHub / GitLab / Bitbucket / Azure
 * DevOps support.
 */
class VpsProvisionCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:vps-provision
                            {--output= : Path for the generated provision.sh (default: ./provision.sh)}
                            {--with-readme : Also write the operator README.md next to the script}
                            {--force : Overwrite existing files}
                            {--stdout : Print the script to stdout instead of writing a file}';

    protected $description = 'Generate the standalone VPS provisioning script (provision.sh) to deploy on a server';

    public function handle(): int
    {
        $sourceDir = dirname(__DIR__, 3).'/resources/larapilot/vps';
        $script = $sourceDir.'/provision.sh';
        $readme = $sourceDir.'/README.md';

        if (! is_file($script)) {
            return $this->failure(
                'E_NOT_FOUND',
                'The bundled provision.sh resource is missing from the package.',
                $this->exitForCode('E_NOT_FOUND'),
                'Reinstall larapilot via Composer.'
            );
        }

        $contents = (string) file_get_contents($script);

        if ((bool) $this->option('stdout')) {
            $this->output->writeln($contents, 0);

            return self::SUCCESS;
        }

        $target = $this->resolvePath($this->stringOption('output') ?? 'provision.sh');

        $written = [];
        $skipped = [];

        if (is_file($target) && ! (bool) $this->option('force')) {
            $skipped[] = $target;
        } else {
            AtomicFile::write($target, $contents);
            @chmod($target, 0o755);
            $written[] = $target;
        }

        $readmeTarget = null;

        if ((bool) $this->option('with-readme') && is_file($readme)) {
            $readmeTarget = dirname($target).'/VPS-README.md';

            if (is_file($readmeTarget) && ! (bool) $this->option('force')) {
                $skipped[] = $readmeTarget;
            } else {
                AtomicFile::write($readmeTarget, (string) file_get_contents($readme));
                $written[] = $readmeTarget;
            }
        }

        return $this->success('vps-provision', [
            'written' => $written,
            'skipped' => $skipped,
            'script_path' => in_array($target, $written, true) ? $target : null,
            'readme_path' => $readmeTarget !== null && in_array($readmeTarget, $written, true) ? $readmeTarget : null,
            'bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'hint' => $skipped !== []
                ? 'Kept existing '.implode(' and ', $skipped).'. Re-run with --force to overwrite.'
                : 'Copy it to the server, then: bash '.basename($target).' && sudo prj-ai config',
        ]);
    }

    protected function resolvePath(string $path): string
    {
        if ($path === '' || $path === '.' || str_ends_with($path, '/')) {
            $path = rtrim($path, '/').'/provision.sh';
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return rtrim((string) getcwd(), '/').'/'.ltrim($path, '/');
    }

    protected function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
