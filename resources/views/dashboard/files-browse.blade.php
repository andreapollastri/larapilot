@extends('larapilot::dashboard.layout')

@section('title', $name.' · File manager')

@push('styles')
<style>
    .crumbs {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 2px 4px;
        margin: 0 0 14px;
        padding: 0;
        list-style: none;
        color: var(--muted);
        font-size: 0.84rem;
    }

    .crumbs li { display: inline-flex; align-items: center; gap: 4px; min-width: 0; }
    .crumbs li + li::before { content: '/'; color: var(--border-strong); }
    .crumbs a { color: var(--muted); overflow-wrap: anywhere; }
    .crumbs a:hover { color: var(--accent); }
    .crumbs [aria-current] { color: var(--text); font-weight: 600; overflow-wrap: anywhere; }

    .root-tabs {
        display: flex;
        gap: 6px;
        margin: 0 calc(var(--gutter) * -1) 18px;
        padding: 2px var(--gutter);
        overflow-x: auto;
        scrollbar-width: none;
    }

    .root-tabs::-webkit-scrollbar { display: none; }

    .root-tabs a {
        flex: none;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 36px;
        padding: 0 13px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface);
        color: var(--text-2);
        font-size: 0.84rem;
        font-weight: 550;
        text-decoration: none;
    }

    .root-tabs a .icon { width: 15px; height: 15px; color: var(--muted); }
    .root-tabs a:hover { border-color: var(--border-strong); color: var(--text); }

    .root-tabs a.is-active {
        border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
        background: var(--accent-soft);
        color: var(--accent-strong);
        font-weight: 600;
    }

    .root-tabs a.is-active .icon { color: var(--accent); }

    .files-head h2 { overflow-wrap: anywhere; }
    .files-head .folder-path { padding: 0; background: transparent; color: var(--muted); font-size: 0.78rem; overflow-wrap: anywhere; }

    .files-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    @media (min-width: 1000px) {
        .files-layout { grid-template-columns: 264px minmax(0, 1fr); gap: 20px; }
    }

    /* ---- tree ---- */
    .tree { padding: 0; }

    .tree > summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }

    .tree > summary::-webkit-details-marker { display: none; }
    .tree > summary > .icon { width: 15px; height: 15px; transition: transform 0.15s ease; }
    .tree[open] > summary > .icon { transform: rotate(90deg); }

    .tree-body { padding: 0 8px 12px; }

    @media (min-width: 1000px) {
        .tree {
            position: sticky;
            top: 24px;
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .tree > summary { cursor: default; pointer-events: none; }
        .tree > summary > .icon { display: none; }
    }

    .tree-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .tree-children,
    .tree-list .tree-list {
        margin-left: 13px;
        padding-left: 6px;
        border-left: 1px solid var(--border);
    }

    .tree-row {
        display: flex;
        align-items: center;
        gap: 2px;
        min-height: 34px;
        padding: 0 6px 0 2px;
        border-radius: var(--radius-xs);
        list-style: none;
        cursor: pointer;
    }

    .tree-row::-webkit-details-marker { display: none; }
    .tree-row:hover { background: var(--surface-3); }
    .tree-row.is-leaf { cursor: default; }

    .tree-row a {
        flex: 1;
        min-width: 0;
        padding: 6px 4px;
        color: var(--text-2);
        font-size: 0.86rem;
        text-decoration: none;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tree-row.is-active { background: var(--accent-soft); }
    .tree-row.is-active a { color: var(--accent-strong); font-weight: 600; }

    .tree-caret {
        display: grid;
        place-items: center;
        flex: none;
        width: 22px;
        height: 22px;
        color: var(--muted);
    }

    .tree-caret .icon { width: 14px; height: 14px; transition: transform 0.15s ease; }
    details[open] > .tree-row .tree-caret .icon { transform: rotate(90deg); }

    .tree-note { margin: 8px 8px 0; color: var(--muted); font-size: 0.76rem; }

    /* ---- upload ---- */
    .dropzone {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 16px;
        padding: 16px;
        border: 1.5px dashed var(--border-strong);
        border-radius: var(--radius);
        background: var(--surface-2);
        transition: border-color 0.14s ease, background-color 0.14s ease;
    }

    .dropzone.is-over {
        border-color: var(--accent);
        background: var(--accent-soft);
    }

    .dropzone-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px 16px;
        flex-wrap: wrap;
    }

    .dropzone-lead { margin: 0; color: var(--text-2); font-size: 0.9rem; }
    .dropzone-lead small { display: block; color: var(--muted); font-size: 0.78rem; }

    .dropzone input[type="file"] {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .dropzone .check {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-2);
        font-size: 0.84rem;
        cursor: pointer;
    }

    .dropzone .check input { width: 16px; height: 16px; margin: 0; accent-color: var(--accent); }

    .upload-progress { display: grid; gap: 8px; }
    .upload-status { margin: 0; color: var(--text-2); font-size: 0.84rem; font-variant-numeric: tabular-nums; }

    .upload-skipped {
        margin: 0;
        padding-left: 1.1rem;
        color: var(--warn);
        font-size: 0.8rem;
        overflow-wrap: anywhere;
    }

    /* ---- listing ---- */
    .listing { overflow: hidden; }

    .listing-head,
    .entry {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 4px 12px;
        align-items: center;
        padding: 10px 14px;
    }

    .listing-head {
        display: none;
        border-bottom: 1px solid var(--border);
        color: var(--muted);
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .entry { border-top: 1px solid var(--border); }
    .listing-head + .entry { border-top: 0; }
    .entry:hover { background: var(--surface-2); }

    .entry-name {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 0;
        color: var(--text);
        font-weight: 550;
        text-decoration: none;
    }

    a.entry-name:hover { color: var(--accent); text-decoration: none; }

    .entry-name > .icon { color: var(--muted); }
    .entry.is-directory .entry-name > .icon { color: var(--accent); }

    .entry-label { min-width: 0; }

    .entry-label span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .entry-label small {
        display: block;
        color: var(--muted);
        font-size: 0.76rem;
        font-weight: 400;
        font-variant-numeric: tabular-nums;
    }

    .entry-size,
    .entry-date {
        display: none;
        color: var(--muted);
        font-size: 0.82rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .entry-actions { display: flex; align-items: center; gap: 2px; }

    .row-btn {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        padding: 0;
        border: 0;
        border-radius: var(--radius-xs);
        background: transparent;
        color: var(--muted);
        cursor: pointer;
        text-decoration: none;
    }

    .row-btn .icon { width: 17px; height: 17px; }
    .row-btn:hover { background: var(--surface-3); color: var(--text); }
    .row-btn.is-danger:hover { background: color-mix(in srgb, var(--danger-fill) 14%, transparent); color: var(--danger); }

    .entry-tag {
        flex: none;
        padding: 1px 8px;
        border-radius: 999px;
        background: var(--surface-3);
        color: var(--muted);
        font-size: 0.64rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    @media (min-width: 760px) {
        .listing-head,
        .entry { grid-template-columns: minmax(0, 1fr) 90px 150px 116px; padding: 8px 16px; }

        .listing-head { display: grid; }
        .entry-size, .entry-date { display: block; }
        .entry-label small { display: none; }
        .entry-actions { justify-content: flex-end; }
        .listing-head > :last-child { text-align: right; }
    }

    .listing-empty { padding: 40px 20px; text-align: center; color: var(--muted); }
    .listing-empty p { margin: 0; }

    .listing-foot {
        padding: 10px 16px;
        border-top: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 0.78rem;
        font-variant-numeric: tabular-nums;
    }

    /* ---- file preview ---- */
    .file-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 18px;
        margin: 8px 0 0;
        padding: 0;
        list-style: none;
        color: var(--muted);
        font-size: 0.82rem;
        font-variant-numeric: tabular-nums;
    }

    .preview { overflow: hidden; }
    .preview-markdown { padding: 22px 18px; }

    @media (min-width: 640px) {
        .preview-markdown { padding: 28px 32px; }
    }

    .preview-text {
        margin: 0;
        padding: 18px;
        max-height: 72vh;
        overflow: auto;
        font-size: 0.8rem;
        line-height: 1.6;
        tab-size: 4;
        scrollbar-width: thin;
    }

    .preview-text code { padding: 0; background: transparent; color: var(--text); font-size: inherit; }

    .preview-image {
        display: grid;
        place-items: center;
        padding: 20px;
        /* a checkerboard, so a transparent logo still shows its edges */
        background:
            conic-gradient(var(--surface-3) 25%, transparent 0 50%, var(--surface-3) 0 75%, transparent 0)
            0 0 / 20px 20px,
            var(--surface);
    }

    /* an SVG with no size of its own would otherwise fill the whole card */
    .preview-image img {
        display: block;
        width: auto;
        height: auto;
        max-width: min(100%, 640px);
        max-height: 60vh;
        object-fit: contain;
    }

    .preview-none { padding: 44px 20px; text-align: center; color: var(--muted); }
    .preview-none p { margin: 0 0 14px; }

    .preview-note {
        margin: 0;
        padding: 10px 16px;
        border-top: 1px solid var(--border);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 0.78rem;
    }

    /* ---- dialogs ---- */
    .modal {
        width: min(calc(100vw - 32px), 440px);
        padding: 0;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface);
        color: var(--text);
        box-shadow: var(--shadow-lg);
    }

    .modal::backdrop { background: rgba(10, 16, 22, 0.5); }

    .modal form { display: grid; gap: 14px; padding: 22px; }
    .modal h3 { margin: 0; font-size: 1.1rem; }
    .modal p { margin: 0; color: var(--text-2); font-size: 0.9rem; overflow-wrap: anywhere; }
    .modal p strong { color: var(--text); font-weight: 600; }
    .modal .field { font-size: 0.7rem; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
</style>
@endpush

@section('content')
    @php
        $rootKey = $root['key'];
        $icons = [
            'directory' => 'folder',
            'image' => 'image',
            'markdown' => 'prd',
            'text' => 'prd',
            'pdf' => 'prd',
            'archive' => 'archive',
            'link' => 'link',
            'other' => 'file',
        ];
        $when = static fn (?int $stamp): string => $stamp === null
            ? '—'
            : \Illuminate\Support\Carbon::createFromTimestamp($stamp)->format('M j, Y · H:i');
    @endphp

    <nav aria-label="Breadcrumb">
        <ol class="crumbs">
            <li><a href="{{ route('larapilot.dashboard.files') }}">File manager</a></li>
            @if ($path === '')
                <li><span aria-current="page">{{ $root['label'] }}</span></li>
            @else
                <li><a href="{{ $browse() }}">{{ $root['label'] }}</a></li>
                @foreach ($breadcrumbs as $crumb)
                    @if ($loop->last)
                        <li><span aria-current="page">{{ $crumb['name'] }}</span></li>
                    @else
                        <li><a href="{{ $browse($crumb['path']) }}">{{ $crumb['name'] }}</a></li>
                    @endif
                @endforeach
            @endif
        </ol>
    </nav>

    <nav class="root-tabs" aria-label="Material folders">
        @foreach ($folders as $folder)
            <a href="{{ route('larapilot.dashboard.files.browse', ['root' => $folder['key']]) }}" @class(['is-active' => $folder['key'] === $rootKey]) title="{{ $folder['description'] }}">@include('larapilot::dashboard.partials.icon', ['name' => 'folder']){{ $folder['label'] }}</a>
        @endforeach
    </nav>

    <header class="page-head files-head">
        <div>
            <h2>{{ $name }}</h2>
            @if ($path === '')
                <p class="sub">{{ $root['description'] }}</p>
            @endif
            <code class="folder-path">{{ $display_path }}</code>
            @unless ($is_directory)
                <ul class="file-meta">
                    <li>{{ $file['size_label'] }}</li>
                    <li>Modified {{ $when($file['modified']) }}</li>
                </ul>
            @endunless
        </div>
        <div class="page-actions">
            @if ($is_directory)
                <button type="button" class="btn" data-upload-pick="files">@include('larapilot::dashboard.partials.icon', ['name' => 'upload'])Upload files</button>
                <button type="button" class="btn ghost" data-upload-pick="folder">@include('larapilot::dashboard.partials.icon', ['name' => 'folder-open'])Upload folder</button>
                <button type="button" class="btn ghost" data-dialog="folder-dialog">@include('larapilot::dashboard.partials.icon', ['name' => 'folder-plus'])New folder</button>
            @else
                <a class="btn" href="{{ $raw($path, true) }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])Download</a>
                @if (in_array($preview['kind'], ['image', 'pdf'], true))
                    <a class="btn ghost" href="{{ $raw($path) }}" target="_blank" rel="noopener noreferrer">@include('larapilot::dashboard.partials.icon', ['name' => 'external'])Open</a>
                @endif
                <button type="button" class="btn ghost" data-dialog="rename-dialog" data-path="{{ $path }}" data-name="{{ $name }}" data-open="1">@include('larapilot::dashboard.partials.icon', ['name' => 'pencil'])Rename</button>
                <button type="button" class="btn ghost" data-dialog="delete-dialog" data-path="{{ $path }}" data-name="{{ $name }}" data-kind="file">@include('larapilot::dashboard.partials.icon', ['name' => 'trash'])Delete</button>
            @endif
        </div>
    </header>

    <div class="files-layout">
        <details class="card tree" open data-tree>
            <summary>Folders @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</summary>
            <div class="tree-body">
                <div @class(['tree-row', 'is-leaf', 'is-active' => $directory === '' && $is_directory])>
                    <span class="tree-caret">@include('larapilot::dashboard.partials.icon', ['name' => 'folder'])</span>
                    <a href="{{ $browse() }}">{{ $root['label'] }}</a>
                </div>
                @if ($tree['nodes'] !== [])
                    <div class="tree-children">
                        @include('larapilot::dashboard.partials.files-tree', ['nodes' => $tree['nodes'], 'browse' => $browse])
                    </div>
                @endif
                @if ($tree['truncated'])
                    <p class="tree-note">The tree stops here — open a folder to see what is inside.</p>
                @endif
            </div>
        </details>

        <div>
            @if ($is_directory)
                <div id="upload-result" hidden></div>

                <form
                    class="dropzone"
                    method="post"
                    action="{{ route('larapilot.dashboard.files.upload', ['root' => $rootKey]) }}"
                    enctype="multipart/form-data"
                    data-upload
                    data-limits="{{ json_encode($limits) }}"
                >
                    @csrf
                    <input type="hidden" name="path" value="{{ $directory }}">
                    <input type="file" name="files[]" id="upload-files" multiple data-upload-input="files" aria-label="Choose files to upload">
                    <input type="file" id="upload-folder" webkitdirectory multiple data-upload-input="folder" aria-label="Choose a folder to upload">

                    <div class="dropzone-row">
                        <p class="dropzone-lead">
                            Drop files or folders here
                            <small>A folder keeps its structure. Up to {{ $limits['file_label'] }} per file.</small>
                        </p>
                        <label class="check">
                            <input type="checkbox" name="replace" value="1" data-upload-replace>
                            Replace existing files
                        </label>
                    </div>

                    <noscript>
                        <div class="dropzone-row">
                            <label class="btn ghost" for="upload-files">Choose files</label>
                            <button type="submit" class="btn">Upload</button>
                        </div>
                    </noscript>

                    <div class="upload-progress" data-upload-progress hidden>
                        <div class="bar-track"><div class="bar-fill" data-upload-bar style="width: 0%"></div></div>
                        <p class="upload-status" data-upload-status role="status"></p>
                    </div>
                </form>

                <section class="card listing" aria-label="Contents of {{ $name }}">
                    @if ($entries === [])
                        <div class="listing-empty">
                            <p>This folder is empty. Drop something in to get started.</p>
                        </div>
                    @else
                        <div class="listing-head" aria-hidden="true">
                            <span>Name</span>
                            <span>Size</span>
                            <span>Modified</span>
                            <span>Actions</span>
                        </div>
                        @foreach ($entries as $entry)
                            @php
                                $isFolder = $entry['type'] === 'directory';
                                $detail = $isFolder
                                    ? $entry['items'].' '.($entry['items'] === 1 ? 'item' : 'items')
                                    : $entry['size_label'];
                            @endphp
                            <div @class(['entry', 'is-directory' => $isFolder])>
                                @if ($entry['link'])
                                    <span class="entry-name" title="A link is listed, never followed.">
                                        @include('larapilot::dashboard.partials.icon', ['name' => 'link'])
                                        <span class="entry-label"><span>{{ $entry['name'] }}</span><small>Link</small></span>
                                        <span class="entry-tag">Link</span>
                                    </span>
                                @else
                                    <a class="entry-name" href="{{ $browse($entry['path']) }}">
                                        @include('larapilot::dashboard.partials.icon', ['name' => $icons[$entry['kind']] ?? 'file'])
                                        <span class="entry-label"><span>{{ $entry['name'] }}</span><small>{{ $detail }} · {{ $when($entry['modified']) }}</small></span>
                                        @if ($entry['packaged'])
                                            <span class="entry-tag" title="Shipped with Larapilot. larapilot:update rewrites it — keep your own system in a folder beside it.">Packaged</span>
                                        @endif
                                    </a>
                                @endif
                                <span class="entry-size">{{ $isFolder ? $detail : $entry['size_label'] }}</span>
                                <span class="entry-date">{{ $when($entry['modified']) }}</span>
                                <span class="entry-actions">
                                    @if (! $isFolder && ! $entry['link'])
                                        <a class="row-btn" href="{{ $raw($entry['path'], true) }}" title="Download" aria-label="Download {{ $entry['name'] }}">@include('larapilot::dashboard.partials.icon', ['name' => 'download'])</a>
                                    @endif
                                    <button type="button" class="row-btn" data-dialog="rename-dialog" data-path="{{ $entry['path'] }}" data-name="{{ $entry['name'] }}" title="Rename" aria-label="Rename {{ $entry['name'] }}">@include('larapilot::dashboard.partials.icon', ['name' => 'pencil'])</button>
                                    <button type="button" class="row-btn is-danger" data-dialog="delete-dialog" data-path="{{ $entry['path'] }}" data-name="{{ $entry['name'] }}" data-kind="{{ $isFolder ? 'folder' : 'file' }}" data-items="{{ $entry['items'] }}" title="Delete" aria-label="Delete {{ $entry['name'] }}">@include('larapilot::dashboard.partials.icon', ['name' => 'trash'])</button>
                                </span>
                            </div>
                        @endforeach
                        <div class="listing-foot">
                            {{ $totals['folders'] }} {{ $totals['folders'] === 1 ? 'folder' : 'folders' }}
                            · {{ $totals['files'] }} {{ $totals['files'] === 1 ? 'file' : 'files' }}
                        </div>
                    @endif
                </section>
            @else
                <section class="card preview" aria-label="Preview of {{ $name }}">
                    @if ($preview['kind'] === 'image')
                        <div class="preview-image">
                            <img src="{{ $raw($path) }}" alt="{{ $name }}">
                        </div>
                    @elseif ($preview['kind'] === 'markdown')
                        <div class="preview-markdown markdown">{!! $preview['html'] !!}</div>
                    @elseif ($preview['kind'] === 'text')
                        @if ($preview['text'] === '')
                            <div class="preview-none"><p>This file is empty.</p></div>
                        @else
                            <pre class="preview-text"><code>{{ $preview['text'] }}</code></pre>
                        @endif
                        @if ($preview['truncated'])
                            <p class="preview-note">Only the beginning is shown. Download the file to read all of it.</p>
                        @endif
                    @else
                        <div class="preview-none">
                            <p>{{ $preview['kind'] === 'pdf' ? 'A PDF opens in its own tab.' : 'There is no preview for this kind of file.' }}</p>
                            @if ($preview['kind'] === 'pdf')
                                <a class="btn ghost" href="{{ $raw($path) }}" target="_blank" rel="noopener noreferrer">Open the PDF</a>
                            @else
                                <a class="btn ghost" href="{{ $raw($path, true) }}">Download</a>
                            @endif
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>

    <dialog class="modal" id="folder-dialog" aria-labelledby="folder-dialog-title">
        <form method="post" action="{{ route('larapilot.dashboard.files.folder', ['root' => $rootKey]) }}">
            @csrf
            <input type="hidden" name="path" value="{{ $directory }}">
            <h3 id="folder-dialog-title">New folder</h3>
            <label class="field">
                Name
                <input type="text" name="name" maxlength="200" required autocomplete="off" data-autofocus>
            </label>
            <div class="modal-actions">
                <button type="button" class="btn ghost" data-dialog-close>Cancel</button>
                <button type="submit" class="btn">Create</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal" id="rename-dialog" aria-labelledby="rename-dialog-title">
        <form method="post" action="{{ route('larapilot.dashboard.files.rename', ['root' => $rootKey]) }}">
            @csrf
            <input type="hidden" name="path" data-field="path">
            <input type="hidden" name="open" value="0" data-field="open">
            <h3 id="rename-dialog-title">Rename</h3>
            <label class="field">
                New name
                <input type="text" name="name" maxlength="200" required autocomplete="off" data-field="name" data-autofocus>
            </label>
            <div class="modal-actions">
                <button type="button" class="btn ghost" data-dialog-close>Cancel</button>
                <button type="submit" class="btn">Rename</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal" id="delete-dialog" aria-labelledby="delete-dialog-title">
        <form method="post" action="{{ route('larapilot.dashboard.files.delete', ['root' => $rootKey]) }}">
            @csrf
            <input type="hidden" name="path" data-field="path">
            <h3 id="delete-dialog-title">Delete</h3>
            <p data-field="message"></p>
            <div class="modal-actions">
                <button type="button" class="btn ghost" data-dialog-close data-autofocus>Cancel</button>
                <button type="submit" class="btn danger">Delete</button>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
<script>
    (() => {
        // On a phone the folder tree starts folded, so the listing comes first.
        document.querySelectorAll('[data-tree]').forEach((tree) => {
            const wide = window.matchMedia('(min-width: 1000px)');
            const sync = () => {
                tree.open = wide.matches;
            };

            sync();
            wide.addEventListener?.('change', sync);
        });

        // ---- dialogs ----
        document.querySelectorAll('[data-dialog]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const dialog = document.getElementById(trigger.dataset.dialog);

                if (!dialog || typeof dialog.showModal !== 'function') {
                    return;
                }

                const set = (field, value) => {
                    const node = dialog.querySelector('[data-field="' + field + '"]');

                    if (!node) {
                        return;
                    }

                    if ('value' in node) {
                        node.value = value;
                    } else {
                        node.textContent = value;
                    }
                };

                const name = trigger.dataset.name || '';

                set('path', trigger.dataset.path || '');
                set('name', name);
                set('open', trigger.dataset.open === '1' ? '1' : '0');

                const message = dialog.querySelector('[data-field="message"]');

                if (message) {
                    const items = Number(trigger.dataset.items || 0);
                    const strong = document.createElement('strong');
                    strong.textContent = name;

                    message.textContent = '';
                    message.append('Delete ', strong);
                    message.append(
                        trigger.dataset.kind === 'folder' && items > 0
                            ? ' and the ' + items + (items === 1 ? ' item' : ' items') + ' inside it? This cannot be undone from here.'
                            : '? This cannot be undone from here.'
                    );
                }

                dialog.showModal();

                const focus = dialog.querySelector('[data-autofocus]');

                if (focus) {
                    focus.focus();

                    if (focus.select && focus.value) {
                        // Leave the extension out of the selection: the name is what changes.
                        const dot = focus.value.lastIndexOf('.');
                        focus.setSelectionRange(0, dot > 0 ? dot : focus.value.length);
                    }
                }
            });
        });

        document.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog')?.close());
        });

        document.querySelectorAll('dialog.modal').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });

        // ---- what the last upload did, shown once after the reload ----
        const result = document.getElementById('upload-result');
        const STORE = 'larapilot-upload';

        try {
            const saved = JSON.parse(sessionStorage.getItem(STORE) || 'null');
            sessionStorage.removeItem(STORE);

            if (saved && result) {
                const failed = saved.stored === 0;
                const title = document.createElement('strong');
                title.textContent = (saved.stored === 1 ? '1 file uploaded.' : saved.stored + ' files uploaded.')
                    + (saved.skipped.length ? ' ' + (saved.skipped.length === 1 ? '1 was skipped.' : saved.skipped.length + ' were skipped.') : '');

                result.className = 'flash ' + (failed ? 'flash--error' : (saved.skipped.length ? 'flash--warn' : 'flash--success'));
                result.setAttribute('role', failed ? 'alert' : 'status');
                result.append(title);

                if (saved.skipped.length) {
                    const list = document.createElement('ul');

                    saved.skipped.slice(0, 30).forEach((item) => {
                        const line = document.createElement('li');
                        line.textContent = item.path + ' — ' + item.reason;
                        list.append(line);
                    });

                    if (saved.skipped.length > 30) {
                        const more = document.createElement('li');
                        more.textContent = '… and ' + (saved.skipped.length - 30) + ' more.';
                        list.append(more);
                    }

                    result.append(list);
                }

                result.hidden = false;
            }
        } catch (error) {}

        // ---- uploads ----
        const zone = document.querySelector('[data-upload]');

        if (!zone) {
            return;
        }

        const limits = JSON.parse(zone.dataset.limits || '{}');
        const replace = zone.querySelector('[data-upload-replace]');
        const progress = zone.querySelector('[data-upload-progress]');
        const bar = zone.querySelector('[data-upload-bar]');
        const status = zone.querySelector('[data-upload-status]');
        const token = zone.querySelector('input[name="_token"]').value;
        const directory = zone.querySelector('input[name="path"]').value;
        const inputs = {
            files: zone.querySelector('[data-upload-input="files"]'),
            folder: zone.querySelector('[data-upload-input="folder"]'),
        };
        let busy = false;

        const size = (bytes) => {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        };

        // Requests stay under what PHP accepts: a file count and a body size.
        const batches = (items) => {
            const maxFiles = Math.max(1, Math.min(Number(limits.max_files) || 20, 20));
            const maxBytes = Number(limits.request_bytes) > 0 ? Number(limits.request_bytes) * 0.9 : Infinity;
            const out = [];
            let current = [];
            let bytes = 0;

            items.forEach((item) => {
                if (current.length > 0 && (current.length >= maxFiles || bytes + item.file.size > maxBytes)) {
                    out.push(current);
                    current = [];
                    bytes = 0;
                }

                current.push(item);
                bytes += item.file.size;
            });

            if (current.length > 0) {
                out.push(current);
            }

            return out;
        };

        const send = async (items) => {
            if (busy || items.length === 0) {
                return;
            }

            busy = true;

            const skipped = [];
            const fileLimit = Number(limits.file_bytes) || 0;
            const accepted = items.filter((item) => {
                if (fileLimit > 0 && item.file.size > fileLimit) {
                    skipped.push({ path: item.path, reason: 'Larger than the ' + size(fileLimit) + ' upload limit.' });

                    return false;
                }

                return true;
            });

            const total = accepted.reduce((sum, item) => sum + item.file.size, 0) || 1;
            let sent = 0;
            let stored = 0;
            let processed = 0;

            progress.hidden = false;
            bar.style.width = '0%';

            for (const batch of batches(accepted)) {
                status.textContent = 'Uploading ' + (processed + 1) + '–' + (processed + batch.length) + ' of ' + accepted.length + '…';

                const body = new FormData();
                body.append('path', directory);

                if (replace && replace.checked) {
                    body.append('replace', '1');
                }

                batch.forEach((item) => {
                    body.append('files[]', item.file, item.file.name);
                    body.append('paths[]', item.path);
                });

                let reason = null;

                try {
                    const response = await fetch(zone.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body,
                    });

                    if (response.ok || response.status === 422) {
                        const data = await response.json();
                        stored += (data.stored || []).length;
                        (data.skipped || []).forEach((item) => skipped.push(item));

                        if ((data.stored || []).length === 0 && (data.skipped || []).length === 0 && data.message) {
                            reason = data.message;
                        }
                    } else if (response.status === 413) {
                        reason = 'The server refused the request as too large.';
                    } else if (response.status === 419) {
                        reason = 'The page expired. Reload it and try again.';
                    } else if (response.status === 403) {
                        reason = 'The file manager is read-only here.';
                    } else {
                        reason = 'The server answered with an error (' + response.status + ').';
                    }
                } catch (error) {
                    reason = 'The connection dropped.';
                }

                if (reason !== null) {
                    batch.forEach((item) => skipped.push({ path: item.path, reason }));
                }

                processed += batch.length;
                sent += batch.reduce((sum, item) => sum + item.file.size, 0);
                bar.style.width = Math.min(100, Math.round((sent / total) * 100)) + '%';
            }

            status.textContent = 'Done. Refreshing the list…';

            try {
                sessionStorage.setItem(STORE, JSON.stringify({ stored, skipped }));
            } catch (error) {}

            window.location.reload();
        };

        const fromInput = (input) => [...input.files].map((file) => ({
            file,
            path: file.webkitRelativePath || file.name,
        }));

        Object.values(inputs).forEach((input) => {
            input?.addEventListener('change', () => {
                const items = fromInput(input);
                input.value = '';
                send(items);
            });
        });

        document.querySelectorAll('[data-upload-pick]').forEach((button) => {
            button.addEventListener('click', () => inputs[button.dataset.uploadPick]?.click());
        });

        // A dropped folder arrives as an entry to walk, level by level.
        const walk = (entry, prefix, out) => new Promise((resolve) => {
            if (entry.isFile) {
                entry.file((file) => {
                    out.push({ file, path: prefix + file.name });
                    resolve();
                }, () => resolve());

                return;
            }

            if (!entry.isDirectory) {
                resolve();

                return;
            }

            const reader = entry.createReader();
            const children = [];

            // readEntries hands over a folder in pages; an empty page ends it.
            const read = () => reader.readEntries(async (page) => {
                if (page.length === 0) {
                    for (const child of children) {
                        await walk(child, prefix + entry.name + '/', out);
                    }

                    resolve();

                    return;
                }

                children.push(...page);
                read();
            }, () => resolve());

            read();
        });

        const over = (event) => {
            if (![...(event.dataTransfer?.types || [])].includes('Files')) {
                return;
            }

            event.preventDefault();
            zone.classList.add('is-over');
        };

        zone.addEventListener('dragenter', over);
        zone.addEventListener('dragover', over);
        zone.addEventListener('dragleave', (event) => {
            if (!zone.contains(event.relatedTarget)) {
                zone.classList.remove('is-over');
            }
        });

        zone.addEventListener('drop', async (event) => {
            event.preventDefault();
            zone.classList.remove('is-over');

            // Entries and files must be taken before the handler yields.
            const dropped = [...(event.dataTransfer?.items || [])]
                .filter((item) => item.kind === 'file')
                .map((item) => ({
                    entry: typeof item.webkitGetAsEntry === 'function' ? item.webkitGetAsEntry() : null,
                    file: item.getAsFile(),
                }));

            const items = [];

            for (const item of dropped) {
                if (item.entry) {
                    await walk(item.entry, '', items);
                } else if (item.file) {
                    items.push({ file: item.file, path: item.file.name });
                }
            }

            send(items);
        });

        // Dropping beside the zone would otherwise open the file in the tab.
        ['dragover', 'drop'].forEach((type) => {
            window.addEventListener(type, (event) => {
                if ([...(event.dataTransfer?.types || [])].includes('Files') && !zone.contains(event.target)) {
                    event.preventDefault();
                }
            });
        });
    })();
</script>
@endpush
