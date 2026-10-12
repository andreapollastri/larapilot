<?php

declare(strict_types=1);

namespace Larapilot\Console\Commands;

use Larapilot\Services\ConfigService;
use Larapilot\Services\VersionService;
use Larapilot\Support\LarapilotCommand;

class ReleaseNotesCommand extends LarapilotCommand
{
    protected $signature = 'larapilot:release-notes
                            {--semver= : SemVer X.Y.Z; the version the work is on when omitted}
                            {--file= : Markdown to keep as the notes (the draft rewritten in the words of the product)}
                            {--write : Keep the notes in paths.release_notes (vX.Y.Z.md)}';

    protected $description = 'Release notes of a version: drafted from its stories, or kept as the team wrote them';

    public function handle(VersionService $versions, ConfigService $config): int
    {
        if (! $versions->enabled()) {
            return $this->failure(
                'E_PRECONDITION',
                'No version to write notes for: release mode is off and the ledger is empty.',
                $this->exitForCode('E_PRECONDITION'),
                'Enable with: php artisan larapilot:settings-set --release-mode=YES'
            );
        }

        $version = trim((string) ($this->option('semver') ?? ''));
        $version = $version !== '' ? $version : (string) ($versions->current()['version'] ?? '');

        try {
            $found = $version !== '' ? $versions->show($version) : null;
        } catch (\InvalidArgumentException $exception) {
            return $this->failure('E_INVALID_INPUT', $exception->getMessage(), $this->exitForCode('E_INVALID_INPUT'));
        }

        if ($found === null) {
            return $this->failure(
                'E_NOT_FOUND',
                $version !== '' ? "Release {$version} not found." : 'No open version: name one with --semver=.',
                $this->exitForCode('E_NOT_FOUND')
            );
        }

        $file = $this->option('file');
        $markdown = $found['notes'];

        if (is_string($file) && $file !== '') {
            $path = $config->absolutePath($file);

            if (! is_file($path)) {
                return $this->failure('E_NOT_FOUND', "File not found: {$file}", $this->exitForCode('E_NOT_FOUND'));
            }

            $markdown = (string) file_get_contents($path);
        }

        $written = null;

        if ($this->option('write') || (is_string($file) && $file !== '')) {
            $written = $versions->writeNotes($found['version'], $markdown);
        } elseif ($found['notes_written']) {
            // Kept notes are the team's words: they win over the draft.
            $markdown = (string) file_get_contents($found['notes_path']);
        }

        return $this->success('release_notes', [
            'version' => $found['version'],
            'status' => $found['status'],
            'markdown' => $markdown,
            'draft' => $found['notes'],
            'path' => $found['notes_path'],
            'kept' => $written !== null || $found['notes_written'],
            'written' => $written !== null,
            'open' => $versions->openStories($found['version']),
        ]);
    }
}
