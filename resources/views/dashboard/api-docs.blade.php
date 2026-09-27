@extends('larapilot::dashboard.layout')

@section('title', 'API Docs')

@push('styles')
<style>
    .api-docs-link { overflow-wrap: anywhere; }

    /* Swagger UI ships a light theme only: give it a light sheet in both modes. */
    .swagger-wrap {
        overflow: hidden;
        background: #ffffff;
        color: #17212b;
        color-scheme: light;
    }

    #swagger-ui { min-height: 720px; }
    #swagger-ui .topbar { display: none; }
</style>
@endpush

@section('content')
    <header class="page-head api-docs-intro">
        <div>
            <h2>API</h2>
            <p class="sub">
                Read-only JSON API for the Larapilot workflow. Same access rules as this dashboard — available in local/staging, disabled in production.
                OpenAPI spec: <a class="api-docs-link" href="{{ route('larapilot.api.openapi') }}">{{ route('larapilot.api.openapi') }}</a>
            </p>
        </div>
    </header>

    <section class="card swagger-wrap">
        <div id="swagger-ui"></div>
    </section>
@endsection

@push('scripts')
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function () {
            SwaggerUIBundle({
                url: @json($openapiUrl),
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                layout: 'StandaloneLayout',
            });
        };
    </script>
@endpush
