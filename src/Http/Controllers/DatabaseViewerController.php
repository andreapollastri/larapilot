<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DatabaseAnonymizer;
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
        protected DatabaseAnonymizer $anonymizer,
        protected DashboardSession $session,
    ) {}

    public function index(Request $request): View
    {
        $this->guard();
        $this->anonymizer->engage($request->boolean('anonymize'));

        if ($request->query('view') === 'migrations') {
            $overview = $this->database->overview();

            return view('larapilot::dashboard.database', $this->protect($overview + $this->downloadFlags() + [
                'view' => 'migrations',
                'migrations' => $overview['error'] === null ? $this->migrations->status() : null,
            ]));
        }

        if ($request->query('view') !== 'diagram') {
            return view('larapilot::dashboard.database', $this->protect($this->database->overview() + $this->downloadFlags()));
        }

        $keysOnly = $request->query('columns') === 'keys';

        return view('larapilot::dashboard.database', $this->protect($this->diagrams->diagram($keysOnly) + $this->downloadFlags() + [
            'view' => 'diagram',
            'keys_only' => $keysOnly,
            'limit' => DatabaseDiagramService::LIMIT,
        ]));
    }

    /**
     * The whole database as one SQL file, written while it downloads —
     * or its structure alone, with `only=structure`.
     */
    public function dump(Request $request): StreamedResponse|RedirectResponse
    {
        $this->guard();
        $this->anonymizer->engage($request->boolean('anonymize'));

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
    public function migrations(Request $request): StreamedResponse|RedirectResponse
    {
        $this->guard();
        $this->anonymizer->engage($request->boolean('anonymize'));

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

        return response()->streamDownload(function () use ($files): void {
            $zip = new ZipStream(static function (string $chunk): void {
                echo $chunk;
            });

            foreach ($files as $path => $contents) {
                $zip->add($path, $contents);
            }

            if ($this->anonymizer->active()) {
                $zip->add('database/migrations/ANONYMIZATION.txt', "These migrations are the structure only: columns, keys, and indexes. They hold no row to replace.\nThe rows are anonymized in the SQL dump and in the Laravel seeders, from the map an admin saves at /larapilot/anonymization (.larapilot/anonymization.yaml).\n");
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
        $this->anonymizer->engage($request->boolean('anonymize'));

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
        $this->anonymizer->engage($request->boolean('anonymize'));

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
            return view('larapilot::dashboard.database', $this->protect($data + $this->downloadFlags()));
        }

        // The rows as the database holds them stay here: the page gets what is written from them.
        $records = $data['records'] ?? [];
        unset($data['records']);

        $tab = $request->query('tab') === 'structure' ? 'structure' : 'rows';

        return view('larapilot::dashboard.database-table', $this->protect($data + $this->downloadFlags() + [
            'tab' => $tab,
            'definition' => $tab === 'structure' ? $this->dumps->definition($table) : [],
            'inserts' => $tab === 'rows' && $data['object']['kind'] === 'table' ? $this->dumps->inserts($data['object'], $records, max(1, (int) $data['from'])) : [],
        ]));
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
     * @return array{credentials_allowed: bool, anonymized: bool, anonymize_optional: bool}
     */
    protected function downloadFlags(): array
    {
        return [
            'credentials_allowed' => $this->credentialsAllowed(),
            'anonymized' => $this->anonymizer->active(),
            'anonymize_optional' => ! $this->anonymizer->required(),
        ];
    }

    /**
     * Where the database lives — its host, its port, its name — is the
     * environment of the server: an admin's to see, not a developer's.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function protect(array $data): array
    {
        if ($this->session->canSeeSecrets() || ! is_array($data['connection'] ?? null)) {
            return $data;
        }

        // The message of a failed connection quotes them too.
        if (is_string($data['error'] ?? null)) {
            $config = config('database.connections.'.$this->database->connectionName());
            $values = array_filter(
                array_map(static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', is_array($config) ? [$config['database'] ?? '', $config['host'] ?? '', $config['username'] ?? '', $data['connection']['database'], (string) ($data['connection']['host'] ?? '')] : []),
                static fn (string $value): bool => mb_strlen($value) >= 3
            );

            $data['error'] = str_replace($values, '[hidden]', $data['error']);
        }

        $data['connection']['database'] = '';
        $data['connection']['host'] = null;

        return $data;
    }

    /**
     * Passwords, tokens, and secrets leave the server in a dump only from
     * a developer's own machine — the environments the dashboard leaves
     * open — never from a shared host, sign-in or not.
     */
    protected function credentialsAllowed(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    protected function guard(): void
    {
        if (! $this->config->databaseViewerBrowsable()) {
            abort(404);
        }
    }
}
