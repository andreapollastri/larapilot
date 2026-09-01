<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapilot\Services\ApiService;
use Larapilot\Services\BackstageService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\DiagnosticsService;
use Larapilot\Services\InternalFeedbackService;
use Larapilot\Services\MetricsService;
use Larapilot\Services\OpenApiService;
use Larapilot\Services\SpecService;
use Larapilot\Support\SpecCode;

class ApiController
{
    public function __construct(
        protected ConfigService $config,
        protected ApiService $api,
        protected BackstageService $backstage,
        protected OpenApiService $openApi,
        protected SpecService $specs,
        protected InternalFeedbackService $feedback,
        protected DiagnosticsService $diagnostics,
        protected MetricsService $metrics,
    ) {}

    public function board(Request $request): JsonResponse
    {
        $this->guard();

        return $this->cacheable($request, $this->api->board());
    }

    public function specs(Request $request): JsonResponse
    {
        $this->guard();

        $status = $request->query('status');
        [$page, $perPage] = $this->pagination($request);

        return $this->cacheable($request, $this->api->specs(
            is_string($status) && $status !== '' ? $status : null,
            $page,
            $perPage,
        ));
    }

    public function spec(Request $request, string $code): JsonResponse
    {
        $this->guard();

        if (! SpecCode::isValid($code)) {
            abort(404);
        }

        $data = $this->api->spec($code);

        if ($data === null) {
            abort(404);
        }

        return $this->cacheable($request, $data);
    }

    public function metricsSnapshot(Request $request): JsonResponse
    {
        $this->guard();

        return $this->cacheable($request, $this->metrics->snapshot());
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

    public function prd(Request $request): JsonResponse
    {
        $this->guard();

        $data = $this->api->prd();

        if ($data === null) {
            abort(404);
        }

        return $this->cacheable($request, $data);
    }

    public function backstage(Request $request): JsonResponse
    {
        $this->guard();
        $this->guardBackstage();

        return response()->json($this->backstage->bundle($this->apiBaseUrl($request)));
    }

    public function backstageCatalog(Request $request): Response
    {
        $this->guard();
        $this->guardBackstage();

        return response($this->backstage->catalogYaml($this->apiBaseUrl($request)), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
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

    /**
     * JSON response with a content-derived ETag so pollers (Backstage, CI
     * dashboards) can revalidate cheaply with `If-None-Match` → `304`.
     *
     * @param  array<string, mixed>  $data
     */
    protected function cacheable(Request $request, array $data): JsonResponse
    {
        $response = response()->json($data);

        $response->setEtag(hash('xxh128', (string) $response->getContent()));
        $response->headers->set('Cache-Control', 'private, must-revalidate');
        $response->isNotModified($request);

        return $response;
    }

    /**
     * `?page` (>= 1) and `?per_page` (1-200, default 50). Out-of-range values
     * are clamped rather than rejected.
     *
     * @return array{int, int}
     */
    protected function pagination(Request $request): array
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = (int) $request->query('per_page', '50');
        $perPage = max(1, min(200, $perPage === 0 ? 50 : $perPage));

        return [$page, $perPage];
    }

    protected function guard(): void
    {
        if (! $this->config->dashboardBrowsable()) {
            abort(404);
        }
    }

    protected function guardBackstage(): void
    {
        if (! $this->backstage->enabled()) {
            abort(404);
        }
    }
}
