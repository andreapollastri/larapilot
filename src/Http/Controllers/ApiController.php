<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Larapilot\Services\ApiService;
use Larapilot\Services\CompanionService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DiagnosticsService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\OpenApiService;
use Larapilot\Services\SpecService;
use Larapilot\Support\SpecCode;

class ApiController
{
    public function __construct(
        protected ConfigService $config,
        protected ApiService $api,
        protected CompanionService $companion,
        protected OpenApiService $openApi,
        protected SpecService $specs,
        protected InternalFeedbackService $feedback,
        protected DiagnosticsService $diagnostics,
    ) {}

    public function board(): JsonResponse
    {
        $this->guard();

        return response()->json($this->api->board());
    }

    public function specs(Request $request): JsonResponse
    {
        $this->guard();

        $status = $request->query('status');

        return response()->json($this->api->specs(is_string($status) && $status !== '' ? $status : null));
    }

    public function spec(string $code): JsonResponse
    {
        $this->guard();

        if (! SpecCode::isValid($code)) {
            abort(404);
        }

        $data = $this->api->spec($code);

        if ($data === null) {
            abort(404);
        }

        return response()->json($data);
    }

    public function storeComment(Request $request, string $code): JsonResponse
    {
        $this->guard();

        if (! SpecCode::isValid($code)) {
            abort(404);
        }

        if (! $this->config->commentsEnabled()) {
            abort(404);
        }

        $spec = $this->specs->find($code);

        if ($spec === null) {
            abort(404);
        }

        if (! $this->feedback->canComment($spec)) {
            return response()->json([
                'message' => 'Comments are closed for this user story.',
            ], 422);
        }

        $validated = $request->validate([
            'author' => ['required', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:10000'],
            'blocks_merge' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $this->api->storeComment(
                $code,
                $validated['author'],
                $validated['message'],
                (bool) ($validated['blocks_merge'] ?? false)
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if ($result === null) {
            abort(404);
        }

        return response()->json($result, 201);
    }

    public function prd(): JsonResponse
    {
        $this->guard();

        $data = $this->api->prd();

        if ($data === null) {
            abort(404);
        }

        return response()->json($data);
    }

    public function companion(Request $request): JsonResponse
    {
        $this->guard();

        return response()->json($this->companion->bundle($this->apiBaseUrl($request)));
    }

    public function diagnostics(Request $request): JsonResponse
    {
        $this->guard();

        if (! (bool) config('larapilot.diagnostics.enabled', true)) {
            abort(404);
        }

        $lines = $request->query('lines');
        $includeLogs = ! $request->boolean('no_logs');

        return response()->json($this->diagnostics->snapshot(
            is_numeric($lines) ? (int) $lines : null,
            $includeLogs,
        ));
    }

    public function openapi(Request $request): JsonResponse
    {
        $this->guard();

        return response()->json($this->openApi->document($this->apiBaseUrl($request)));
    }

    public function docs(): View
    {
        $this->guard();

        return view('larapilot::dashboard.api-docs', [
            'openapiUrl' => route('larapilot.api.openapi'),
        ]);
    }

    protected function apiBaseUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost().'/'.trim((string) config('larapilot.dashboard_route.prefix', 'larapilot'), '/').'/api';
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }
}
