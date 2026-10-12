<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Larapilot\Services\VersionService;

/**
 * The version scope travels with the person: narrowed to one version, or a
 * few, on the Board, the Plan stays on them, and so do the Forecast and the
 * Versions page, until the scope is cleared. `?version=1.2.0,1.3.0` sets
 * it, `?version=all` clears it; a page asked with no `version` reads the
 * one kept in the session.
 */
trait ScopesVersions
{
    /**
     * Where the session keeps the scope. A method, not a constant: a trait
     * constant needs PHP 8.2.
     */
    protected function versionScopeKey(): string
    {
        return 'larapilot.version_scope';
    }

    /**
     * The query of the request with the version scope in force.
     *
     * @return array<string, mixed>
     */
    protected function scopedQuery(Request $request, VersionService $versions): array
    {
        $query = $request->query();
        $session = $request->hasSession() ? $request->session() : null;

        if (! $versions->enabled()) {
            unset($query['version']);

            return $query;
        }

        if (array_key_exists('version', $query)) {
            $asked = $query['version'];

            if (is_string($asked) && in_array(strtolower(trim($asked)), ['', 'all'], true)) {
                $session?->forget($this->versionScopeKey());
                unset($query['version']);

                return $query;
            }

            $selected = $versions->selection($asked);
            $session?->put($this->versionScopeKey(), $selected);
            $query['version'] = implode(',', $selected);

            return $query;
        }

        $kept = $session?->get($this->versionScopeKey());
        $selected = is_array($kept) ? $versions->selection($kept) : [];

        if ($selected !== []) {
            $query['version'] = implode(',', $selected);
        }

        return $query;
    }
}
