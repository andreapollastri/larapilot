@extends('larapilot::dashboard.layout')

@section('title', 'Dumps · Laravel')

@push('styles')
@include('larapilot::dashboard.partials.laravel-styles')
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Laravel</h2>
            <p class="sub">What <code>dump()</code> and <code>dd()</code> printed, the newest first: the value, the file and line that dumped it, and the request or the command it happened in. A dump still shows where it always did — in the page, the terminal, the response of an API call — and here it stays to be read.</p>
        </div>
        @if ($records !== [])
            <form class="page-actions" method="post" action="{{ route('larapilot.dashboard.laravel.dumps.clear') }}">
                @csrf
                <button type="submit" class="btn ghost">@include('larapilot::dashboard.partials.icon', ['name' => 'trash'])Forget them all</button>
            </form>
        @endif
    </header>

    @include('larapilot::dashboard.partials.laravel-tabs', ['current' => 'dumps'])

    <div class="lv-bar">
        <span @class(['chip', 'live' => $recording, 'stale' => ! $recording])>@include('larapilot::dashboard.partials.icon', ['name' => 'bug']){{ $recording ? 'Every dump is kept' : 'Dumps are not kept here' }}</span>
        <span class="chip">The last {{ number_format($keep) }} in <code>{{ $directory }}</code></span>
    </div>

    @if ($recording && $taken_by !== null)
        <div class="flash flash--warn" role="status">
            <strong>{{ $taken_by }} is taking the dumps before they get here.</strong>
            <span>While it watches, <code>dump()</code> and <code>dd()</code> show in its own window and nothing is kept on this page. Stop it watching to read them here.</span>
        </div>
    @endif

    @if (! $recording)
        <div class="flash flash--warn" role="status">
            @if (app()->environment(['local', 'development']))
                <strong>Keeping the dumps is switched off.</strong>
                <span>Remove <code>LARAPILOT_LARAVEL_VIEWER_DUMPS=false</code> from <code>.env</code> to keep what <code>dump()</code> and <code>dd()</code> print.</span>
            @else
                <strong>By default the dumps are kept only on a developer's own machine.</strong>
                <span>A dump shows whatever the code was holding — a user, a token, a request. <code>LARAPILOT_LARAVEL_VIEWER_DUMPS=true</code> in <code>.env</code> keeps them in <code>{{ app()->environment() }}</code> too.</span>
            @endif
        </div>
    @endif

    @forelse ($records as $record)
        @php $at = \Illuminate\Support\Carbon::parse($record['at'] ?? 'now'); @endphp
        <article class="card lv-dump-entry">
            <div class="lv-item">
                <span class="lv-item-title">
                    @if (($record['file'] ?? null) !== null)
                        @php $place = $record['file'].(($record['line'] ?? null) !== null ? ':'.$record['line'] : ''); @endphp
                        @if ($record['link'] !== null)
                            <a href="{{ $record['link'] }}"><code>{{ $place }}</code></a>
                        @else
                            <code>{{ $place }}</code>
                        @endif
                    @else
                        Dump
                    @endif
                </span>
                <span class="lv-item-when" title="{{ $at->format('Y-m-d H:i:s') }}">{{ $at->diffForHumans() }}</span>
                <span class="lv-item-meta">
                    @if (($record['context'] ?? '') !== '')
                        <code>{{ $record['context'] }}</code>
                    @endif
                    @if (($record['label'] ?? null) !== null)
                        <span>{{ $record['label'] }}</span>
                    @endif
                    @if ($record['cut'] ?? false)
                        <span>Only the start is kept.</span>
                    @endif
                </span>
            </div>
            <pre class="lv-dump">{{ $record['text'] ?? '' }}</pre>
        </article>
    @empty
        <section class="card">
            <div class="empty">
                @if ($recording)
                    <p>No dump yet. Write <code>dump($value)</code> or <code>dd($value)</code> anywhere in the application: what it prints shows up here.</p>
                @else
                    <p>No dump was kept.</p>
                @endif
            </div>
        </section>
    @endforelse
@endsection
