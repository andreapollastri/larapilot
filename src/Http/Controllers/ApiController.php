<?php

declare(strict_types=1);

namespace Larapilot\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Larapilot\Services\ApiService;
use Larapilot\Services\ConfigService;
use Larapilot\Services\OpenApiService;
use Larapilot\Support\SpecCode;

class ApiController
{
    public function __construct(
        protected ConfigService $config,
        protected ApiService $api,
        protected OpenApiService $openApi,
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

    public function prd(): JsonResponse
    {
        $this->guard();

        $data = $this->api->prd();

        if ($data === null) {
            abort(404);
        }

        return response()->json($data);
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
