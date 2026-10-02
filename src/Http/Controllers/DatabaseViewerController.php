<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DatabaseDiagramPdfWriter;
use Larapilot\Services\DatabaseDiagramService;
use Larapilot\Services\DatabaseDumpService;
use Larapilot\Services\DatabaseMigrationExportService;
use Larapilot\Services\DatabaseMigrationService;
use Larapilot\Services\DatabaseSeederExportService;
use Larapilot\Services\DatabaseViewerService;
use Larapilot\Support\ZipStream;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DatabaseViewerController
{
    public function __construct(
        protected ConfigService $config,
        protected DatabaseViewerService $database,
        protected DatabaseDumpService $dumps,
        protected DatabaseDiagramService $diagrams,
        protected DatabaseMigrationService $migrations,
        protected DatabaseMigrationExportService $migrationFiles,
        protected DatabaseSeederExportService $seederFiles,
    ) {}

    public function index(Request $request): View
    {
        $this->guard();

        if ($request->query('view') === 'migrations') {
            $overview = $this->database->overview();

            return view('larapilot::dashboard.database', $overview + [
                'credentials_allowed' => $this->credentialsAllowed(),
                'view' => 'migrations',
                'migrations' => $overview['error'] === null ? $this->migrations->status() : null,
            ]);
        }

        if ($request->query('view') !== 'diagram') {
            return view('larapilot::dashboard.database', $this->database->overview() + [
                'credentials_allowed' => $this->credentialsAllowed(),
            ]);
        }

        $keysOnly = $request->query('columns') === 'keys';

        return view('larapilot::dashboard.database', $this->diagrams->diagram($keysOnly) + [
            'credentials_allowed' => $this->credentialsAllowed(),
            'view' => 'diagram',
            'keys_only' => $keysOnly,
            'limit' => DatabaseDiagramService::LIMIT,
        ]);
    }

    /**
     * The whole database as one SQL file, written while it downloads —
     * or its structure alone, with `only=structure`.
     */
    public function dump(Request $request): StreamedResponse|RedirectResponse
    {
        $this->guard();

        if ($this->database->overview()['error'] !== null) {
            return $this->unreadable();
        }

        $credentials = $request->boolean('credentials') && $this->credentialsAllowed();
        $rows = $request->query('only') !== 'structure';

        return response()->streamDownload(function () use ($credentials, $rows): void {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $this->dumps->dump(static function (string $chunk): void {
                echo $chunk;
            }, $credentials, $rows);
        }, $this->dumps->filename($rows), [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The structure of the database as Laravel migrations, in a zip.
     */
    public function migrations(): StreamedResponse|RedirectResponse
    {
        $this->guard();

        if ($this->database->overview()['error'] !== null) {
            return $this->unreadable();
        }

        try {
            $files = $this->migrationFiles->files();
        } catch (Throwable) {
            return $this->unreadable();
        }

        if ($files === []) {
            return redirect()
                ->route('larapilot.dashboard.database')
                ->with('larapilot_error', 'The database has no table to write a migration for.');
        }

        return response()->streamDownload(static function () use ($files): void {
            $zip = new ZipStream(static function (string $chunk): void {
                echo $chunk;
            });

            foreach ($files as $path => $contents) {
                $zip->add($path, $contents);
            }

            $zip->finish();
        }, $this->migrationFiles->filename(), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The rows of the database as Laravel seeders, in a zip written while
     * it downloads.
     */
    public function seeders(Request $request): StreamedResponse|RedirectResponse
    {
        $this->guard();

        if ($this->database->overview()['error'] !== null) {
            return $this->unreadable();
        }

        $credentials = $request->boolean('credentials') && $this->credentialsAllowed();

        return response()->streamDownload(function () use ($credentials): void {
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $zip = new ZipStream(static function (string $chunk): void {
                echo $chunk;
            });

            $this->seederFiles->write($zip, $credentials);
            $zip->finish();
        }, $this->seederFiles->filename(), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The diagram as a PDF: one page as large as the drawing.
     */
    public function diagram(Request $request, DatabaseDiagramPdfWriter $pdf): Response|RedirectResponse
    {
        $this->guard();

        $data = $this->diagrams->diagram($request->query('columns') === 'keys');

        if ($data['diagram'] === null || $data['diagram']['nodes'] === []) {
            return redirect()
                ->route('larapilot.dashboard.database')
                ->with('larapilot_error', 'There is no diagram of this database to download.');
        }

        $name = $this->database->fileName();
        $relations = count($data['diagram']['edges']);
        $note = implode(' · ', array_filter([
            $data['connection']['driver_label'],
            number_format($data['tables']).' '.($data['tables'] === 1 ? 'table' : 'tables'),
            $data['views'] > 0 ? number_format($data['views']).' '.($data['views'] === 1 ? 'view' : 'views') : null,
            number_format($relations).' '.($relations === 1 ? 'foreign key' : 'foreign keys'),
            Carbon::now()->format('Y-m-d'),
        ]));

        return response($pdf->write($data['diagram'], $name.' — database diagram', $note), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'-diagram-'.Carbon::now()->format('Y-m-d').'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function table(Request $request, string $table): View
    {
        $this->guard();

        $data = $this->database->table($table, [
            'page' => max(1, $request->integer('page', 1)),
            'sort' => $this->text($request, 'sort'),
            'direction' => $this->text($request, 'dir'),
            'search' => $this->text($request, 'q'),
            'where' => $this->text($request, 'where'),
            'is' => $request->has('is') ? (string) $this->text($request, 'is') : null,
        ]);

        if ($data === null) {
            abort(404);
        }

        if ($data['object'] === null) {
            return view('larapilot::dashboard.database', $data + [
                'credentials_allowed' => $this->credentialsAllowed(),
            ]);
        }

        // The rows as the database holds them stay here: the page gets what is written from them.
        $records = $data['records'] ?? [];
        unset($data['records']);

        $tab = $request->query('tab') === 'structure' ? 'structure' : 'rows';

        return view('larapilot::dashboard.database-table', $data + [
            'tab' => $tab,
            'definition' => $tab === 'structure' ? $this->dumps->definition($table) : [],
            'inserts' => $tab === 'rows' && $data['object']['kind'] === 'table' ? $this->dumps->inserts($data['object'], $records, max(1, (int) $data['from'])) : [],
        ]);
    }

    protected function unreadable(): RedirectResponse
    {
        return redirect()
            ->route('larapilot.dashboard.database')
            ->with('larapilot_error', 'The database cannot be read, so there is nothing to download.');
    }

    protected function text(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Passwords, tokens, and secrets leave the server in a dump only from
     * a developer's own machine — never from a shared host, sign-in or not.
     */
    protected function credentialsAllowed(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    protected function guard(): void
    {
        if (! $this->config->databaseViewerBrowsable()) {
            abort(404);
        }
    }
}
