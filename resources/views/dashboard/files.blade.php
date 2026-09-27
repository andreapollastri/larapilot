@extends('larapilot::dashboard.layout')

@section('title', 'File manager')

@push('styles')
<style>
    .folder-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }

    @media (min-width: 640px) {
        .folder-grid { grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); }
    }

    .folder-card {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 20px;
        color: inherit;
        text-decoration: none;
        transition: border-color 0.14s ease, box-shadow 0.14s ease, transform 0.14s ease;
    }

    .folder-card:hover {
        border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
        box-shadow: 0 8px 22px color-mix(in srgb, var(--text) 9%, transparent);
        text-decoration: none;
        transform: translateY(-1px);
    }

    .folder-card-head {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .folder-icon {
        display: grid;
        place-items: center;
        flex: none;
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        background: var(--accent-soft);
        color: var(--accent);
    }

    .folder-icon .icon { width: 20px; height: 20px; }

    .folder-card h3 {
        margin: 0;
        font-size: 1.04rem;
    }

    .folder-card .folder-path {
        padding: 0;
        background: transparent;
        color: var(--muted);
        font-size: 0.74rem;
    }

    .folder-card p {
        margin: 0;
        color: var(--text-2);
        font-size: 0.9rem;
        line-height: 1.5;
    }

    .folder-used {
        color: var(--muted);
        font-size: 0.78rem;
    }

    .folder-stats {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: auto;
        padding-top: 14px;
        border-top: 1px solid var(--border);
        color: var(--muted);
        font-size: 0.8rem;
        font-variant-numeric: tabular-nums;
    }

    .folder-stats .open {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--accent);
        font-weight: 600;
    }

    .folder-stats .open .icon { width: 15px; height: 15px; }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>File manager</h2>
            <p class="sub">The material the skills read before they start. Browse, upload, rename, and delete inside five folders of <code>.larapilot/</code>.</p>
        </div>
    </header>

    <div class="folder-grid">
        @foreach ($folders as $folder)
            <a class="card folder-card" href="{{ route('larapilot.dashboard.files.browse', ['root' => $folder['key']]) }}">
                <div class="folder-card-head">
                    <span class="folder-icon">@include('larapilot::dashboard.partials.icon', ['name' => 'folder'])</span>
                    <div>
                        <h3>{{ $folder['label'] }}</h3>
                        <code class="folder-path">{{ $folder['path'] }}</code>
                    </div>
                </div>
                <p>{{ $folder['description'] }}</p>
                <span class="folder-used">Read by: {{ $folder['used_by'] }}</span>
                <div class="folder-stats">
                    <span>
                        @if ($folder['files'] === 0 && $folder['folders'] === 0)
                            Empty
                        @else
                            {{ $folder['truncated'] ? 'Over ' : '' }}{{ number_format($folder['files']) }} {{ $folder['files'] === 1 ? 'file' : 'files' }}
                            · {{ number_format($folder['folders']) }} {{ $folder['folders'] === 1 ? 'folder' : 'folders' }}
                            · {{ $folder['size_label'] }}
                        @endif
                    </span>
                    <span class="open">Open @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</span>
                </div>
            </a>
        @endforeach
    </div>
@endsection
