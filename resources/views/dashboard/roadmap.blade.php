@extends('larapilot::dashboard.layout')

@section('title', 'Roadmap')

@push('styles')
<style>
    .roadmap-lanes { display: grid; gap: 22px; }
    .roadmap-lane-head { margin-bottom: 10px; }
    .roadmap-lane-head h3 { margin: 0; font-size: 1.05rem; }
    .roadmap-lane-head .hint { margin: 2px 0 0; }
    .roadmap-empty { margin: 0; color: var(--muted); font-size: 0.88rem; }
    .roadmap-version { padding: 16px 18px; margin-bottom: 12px; }
    .roadmap-version-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px 16px; margin-bottom: 10px; }
    .roadmap-label { margin: 0; color: var(--accent); font-size: 0.72rem; font-weight: 650; letter-spacing: 0.06em; text-transform: uppercase; }
    .roadmap-version h4 { margin: 2px 0 4px; font-size: 1.05rem; }
    .roadmap-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .roadmap-group { margin: 12px 0 4px; font-size: 0.82rem; color: var(--text-2); }
    .roadmap-items { margin: 0; padding: 0; list-style: none; }
    .roadmap-items li { padding: 8px 0; border-top: 1px solid var(--border); }
    .roadmap-items li .hint { display: block; margin: 2px 0 0; }
    .roadmap-aside { margin-top: 22px; }
    .roadmap-link { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 10px; }
    .roadmap-link input { flex: 1 1 220px; min-width: 0; font-family: var(--mono); font-size: 0.82rem; }
    .roadmap-left { margin: 8px 0 0; padding-left: 1.1em; color: var(--text-2); }
    .roadmap-left li { margin: 2px 0; }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Roadmap</h2>
            <p class="sub">What {{ $product }} ships, in the words a customer can read. Updated {{ $updated_at }}.</p>
        </div>
    </header>

    @if ($empty)
        <section class="card empty">
            <p>No release in the ledger yet. With release mode on, <code>php artisan larapilot:release-add</code> is what fills this page.</p>
        </section>
    @else
        @include('larapilot::roadmap.lanes', ['lanes' => $lanes, 'team' => true])
    @endif

    <div class="roadmap-aside">
        <section class="card" style="padding: 18px">
            <h3>Public link</h3>
            @if ($public['enabled'] && $public['url'])
                <p class="hint">Anyone with this address sees the roadmap above. It carries no codes, people, hours, or git. The address itself is the key: treat it as a secret.</p>
                <div class="roadmap-link">
                    <input class="control" type="text" value="{{ $public['url'] }}" readonly aria-label="Public roadmap link" onfocus="this.select()" id="roadmap-public-url">
                    <button type="button" class="btn" data-copy-target="#roadmap-public-url">@include('larapilot::dashboard.partials.icon', ['name' => 'copy'])Copy</button>
                    <a class="btn ghost" href="{{ $public['url'] }}" target="_blank" rel="noopener noreferrer">@include('larapilot::dashboard.partials.icon', ['name' => 'external'])Open</a>
                    <form method="post" action="{{ route('larapilot.dashboard.roadmap.regenerate') }}" onsubmit="return confirm('Make a new public link? The one shared so far stops working at once.')">
                        @csrf
                        <button type="submit" class="btn ghost">New link</button>
                    </form>
                </div>
                <p class="hint" style="margin-top: 10px">Wording lives in <code>{{ $public['path'] }}</code>, edited with <code>php artisan larapilot:roadmap-set</code>. Turn the link off with <code>php artisan larapilot:settings-set --public-roadmap=NO</code>.</p>
            @else
                <p class="hint">The public link is off. The roadmap stays on this dashboard. Turn it on with <code>php artisan larapilot:settings-set --public-roadmap=YES</code>, then reload: the address appears here.</p>
            @endif
        </section>

        <section class="card" style="padding: 18px; margin-top: 16px">
            <h3>Left off the public page</h3>
            <p class="hint">The public page is the same lanes, without any of this.</p>
            <ul class="roadmap-left">
                @foreach ($public['left_out'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.querySelector(button.getAttribute('data-copy-target'));

            if (! field) {
                return;
            }

            var label = button.innerHTML;
            var done = function () {
                button.textContent = 'Copied';
                setTimeout(function () { button.innerHTML = label; }, 1500);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(field.value).then(done, function () { field.select(); document.execCommand('copy'); done(); });
            } else {
                field.select();
                document.execCommand('copy');
                done();
            }
        });
    });
</script>
@endpush
