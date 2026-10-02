<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Larapilot\Services\ConfigService;
use Larapilot\Services\LaravelViewerService;

/**
 * The Laravel page of the dashboard: how the framework is set up and what
 * it is doing, one tab each for the overview, the schedule, the queue, the
 * outgoing mail, and the dumps.
 */
class LaravelViewerController
{
    public function __construct(
        protected ConfigService $config,
        protected LaravelViewerService $laravel,
    ) {}

    public function index(): View
    {
        $this->guard();

        return view('larapilot::dashboard.laravel', [
            'drivers' => $this->laravel->drivers(),
            'caches' => $this->laravel->caches(),
            'schedule' => $this->laravel->schedule(),
            'queue' => $this->laravel->queue(),
            'recorded' => $this->laravel->recorded(),
        ]);
    }

    public function schedule(): View
    {
        $this->guard();

        return view('larapilot::dashboard.laravel-schedule', [
            'schedule' => $this->laravel->schedule(),
        ]);
    }

    public function queue(Request $request): View
    {
        $this->guard();

        $connection = $request->query('connection');

        return view('larapilot::dashboard.laravel-queue', [
            'queue' => $this->laravel->queue(is_string($connection) ? $connection : null),
            'listed' => LaravelViewerService::LISTED,
        ]);
    }

    public function mail(): View
    {
        $this->guard();

        return view('larapilot::dashboard.laravel-mail', $this->laravel->mail());
    }

    public function message(string $id): View
    {
        $this->guard();

        $message = $this->laravel->message($id);

        if ($message === null) {
            abort(404);
        }

        return view('larapilot::dashboard.laravel-mail-message', [
            'message' => $message,
        ]);
    }

    public function clearMail(): RedirectResponse
    {
        $this->guard();

        $cleared = $this->laravel->clearMail();

        return redirect()
            ->route('larapilot.dashboard.laravel.mail')
            ->with('larapilot_success', $cleared === 0 ? 'No mail was kept.' : 'Forgot '.$cleared.' '.($cleared === 1 ? 'mail' : 'mails').'.');
    }

    public function dumps(): View
    {
        $this->guard();

        return view('larapilot::dashboard.laravel-dumps', $this->laravel->dumps());
    }

    public function clearDumps(): RedirectResponse
    {
        $this->guard();

        $cleared = $this->laravel->clearDumps();

        return redirect()
            ->route('larapilot.dashboard.laravel.dumps')
            ->with('larapilot_success', $cleared === 0 ? 'No dump was kept.' : 'Forgot '.$cleared.' '.($cleared === 1 ? 'dump' : 'dumps').'.');
    }

    protected function guard(): void
    {
        if (! $this->config->laravelViewerBrowsable()) {
            abort(404);
        }
    }
}
