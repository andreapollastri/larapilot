@extends('larapilot::dashboard.layout')

@section('title', 'API Docs')

@push('styles')
<style>
    .api-docs-intro {
        margin-bottom: 20px;
        padding: 16px 20px;
    }

    .api-docs-intro p {
        margin: 0;
        color: var(--muted);
        font-size: 0.9rem;
    }

    .swagger-wrap {
        overflow: hidden;
    }

    #swagger-ui {
        min-height: 720px;
    }

    #swagger-ui .topbar {
        display: none;
    }
</style>
@endpush

@section('content')
    <section class="card api-docs-intro">
        <p>
            Read-only JSON API for the Larapilot workflow. Same access rules as this dashboard — available in local/staging, disabled in production.
            OpenAPI spec: <a href="{{ route('larapilot.api.openapi') }}">{{ route('larapilot.api.openapi') }}</a>
        </p>
    </section>

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
