<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Larapilot\Services\Auth\AuthAuditService;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DatabaseAnonymizer;
use Larapilot\Services\DatabaseViewerService;

/**
 * The anonymization map, for admins: which column of which table stays,
 * is hidden, or is replaced. The file is `.larapilot/anonymization.yaml`.
 * A developer in production never opens this page; their rows are
 * anonymized from it all the same.
 */
class AnonymizationController
{
    public function __construct(
        protected ConfigService $config,
        protected DatabaseViewerService $database,
        protected DatabaseAnonymizer $anonymizer,
        protected DashboardSession $session,
        protected AuthAuditService $audit,
    ) {}

    public function index(): View
    {
        $this->guard();

        $form = $this->anonymizer->tablesForForm($this->database);

        $locale = $this->anonymizer->locale();
        $groups = ['Strategy' => DatabaseAnonymizer::STRATEGIES] + DatabaseAnonymizer::CATALOG;
        $local = $this->anonymizer->localFormatters($locale);

        if ($local !== []) {
            $groups['Only in '.$locale] = $local;
        }

        return view('larapilot::dashboard.anonymization', [
            'path' => $this->anonymizer->relativePath(),
            'tables' => $form['tables'],
            'error' => $form['error'],
            'broken' => $this->anonymizer->broken(),
            'rules' => $this->anonymizer->rules(),
            'groups' => $groups,
            'labels' => array_merge(...array_values($groups)),
            'locale' => $locale,
            'locales' => $this->anonymizer->locales(),
            'production' => app()->environment('production'),
        ]);
    }

    public function locale(Request $request): RedirectResponse
    {
        $this->guard();

        $locale = trim((string) $request->input('locale', ''));

        try {
            $this->anonymizer->saveLocale($locale);
        } catch (InvalidArgumentException $exception) {
            return $this->failed('The locale was not changed.', $exception->getMessage());
        }

        $this->audit->record('anonymization.locale', [
            'locale' => $locale,
            'actor' => $this->session->name() ?? 'dashboard',
        ]);

        return redirect()
            ->route('larapilot.dashboard.anonymization')
            ->with('larapilot_success', 'Replacements are now written in '.($this->anonymizer->locales()[$locale] ?? $locale).'.');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->guard();

        $table = trim((string) $request->input('table', ''));
        $columns = $request->input('columns', []);

        if ($table === '' || ! is_array($columns)) {
            return $this->failed('The map was not saved.', 'Choose a table.');
        }

        try {
            $count = $this->anonymizer->saveTable($table, $columns, $this->database);
        } catch (InvalidArgumentException $exception) {
            return $this->failed('The map was not saved.', $exception->getMessage());
        }

        $this->audit->record('anonymization.saved', [
            'table' => $table,
            'columns' => $count,
            'actor' => $this->session->name() ?? 'dashboard',
        ]);

        $message = $count === 0
            ? "{$table} follows the safe default."
            : "{$table} saved with {$count} ".($count === 1 ? 'column' : 'columns').' of its own.';

        return redirect()
            ->to(route('larapilot.dashboard.anonymization').'#map-'.rawurlencode($table))
            ->with('larapilot_success', $message);
    }

    protected function failed(string $message, string $detail): RedirectResponse
    {
        return redirect()
            ->route('larapilot.dashboard.anonymization')
            ->with('larapilot_error', $message)
            ->with('larapilot_details', [$detail]);
    }

    protected function guard(): void
    {
        if (! $this->config->databaseViewerBrowsable()) {
            abort(404);
        }
    }
}
