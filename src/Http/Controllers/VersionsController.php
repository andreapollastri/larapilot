<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapilot\Http\Controllers\Concerns\ScopesVersions;
use Larapilot\Services\ConfigService;
use Larapilot\Services\VersionService;

/**
 * The versions of the product: every version of the ledger with what it
 * holds, what it has delivered, and when its last story is forecast to
 * land; one page per version with its stories, its epics, and its release
 * notes.
 */
class VersionsController
{
    use ScopesVersions;

    public function __construct(
        protected ConfigService $config,
        protected VersionService $versions,
    ) {}

    public function index(Request $request): View
    {
        $this->guard();

        $query = $this->scopedQuery($request, $this->versions);
        $selected = isset($query['version']) ? $this->versions->selection($query['version']) : [];
        $rows = $this->versions->enabled() ? $this->versions->rows() : [];

        return view('larapilot::dashboard.versions', [
            'enabled' => $this->versions->enabled(),
            'releaseMode' => $this->config->releaseModeEnabled(),
            'rows' => $rows,
            'current' => $rows !== [] ? $this->versions->current() : null,
            'unassigned' => $this->versions->unassigned(),
            'scope' => ['versions' => $rows, 'selected' => $selected],
        ]);
    }

    public function show(string $version): View
    {
        $this->guard();

        return view('larapilot::dashboard.version', ['version' => $this->find($version)]);
    }

    /**
     * The release notes of a version, as the team keeps them when they are
     * written, else as the backlog drafts them.
     */
    public function notes(string $version): Response
    {
        $this->guard();

        $found = $this->find($version);
        $markdown = $found['notes_written'] ? (string) file_get_contents($found['notes_path']) : $found['notes'];

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="release-notes-v'.$found['version'].'.md"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function find(string $version): array
    {
        try {
            $found = $this->versions->show($version);
        } catch (\InvalidArgumentException) {
            $found = null;
        }

        if ($found === null) {
            abort(404);
        }

        return $found;
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
