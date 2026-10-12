@extends('larapilot::dashboard.layout')

@section('title', 'API token')

@push('styles')
<style>
    .token-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1000px) { .token-grid { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); } }
    .token-grid .card { padding: 20px; }
    .token-grid h3 { margin: 0 0 6px; font-size: 1.02rem; }
    .token-state { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
    .token-state.on { background: color-mix(in srgb, var(--status-done) 16%, transparent); color: var(--status-done); }
    .token-state.off { background: var(--surface-3); color: var(--muted); }
    .facts { display: grid; gap: 8px; margin: 12px 0 0; }
    .facts > div { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 10px; }
    .facts dt { color: var(--muted); font-size: 0.82rem; }
    .facts dd { margin: 0; overflow-wrap: anywhere; }
    .token-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
    .token-fresh { margin: 0 0 16px; padding: 14px; border: 1px solid color-mix(in srgb, var(--status-done) 45%, var(--border)); border-radius: var(--radius-sm); background: color-mix(in srgb, var(--status-done) 7%, transparent); }
    .token-fresh strong { display: block; margin-bottom: 8px; }
    .token-fresh .row { display: flex; flex-wrap: wrap; gap: 8px; }
    .token-fresh input { flex: 1 1 260px; min-width: 0; font-family: var(--mono); font-size: 0.9rem; }
    .token-fresh .hint { margin: 8px 0 0; }
    pre.snippet { margin: 10px 0 0; padding: 12px 14px; overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface-3); font-size: 0.8rem; line-height: 1.5; }
    pre.snippet code { padding: 0; background: none; color: var(--text); }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>API token</h2>
            <p class="sub">The key of the <a href="{{ $docs_url }}">JSON API</a> at <code>/larapilot/api/*</code> — for scripts, Backstage, a CI job. It is shown once, when made; only its hash is kept in <code>{{ $path }}</code>, out of Git. Rotating it makes the old one stop at once.</p>
        </div>
    </header>

    <div class="token-grid">
        <section class="card">
            @if (is_string($fresh) && $fresh !== '')
                <div class="token-fresh" role="status">
                    <strong>Your new token — copy it now, it is not shown again.</strong>
                    <div class="row">
                        <input class="control" type="text" value="{{ $fresh }}" readonly id="fresh-token" aria-label="The new API token" onfocus="this.select()">
                        <button type="button" class="btn" data-copy="#fresh-token">@include('larapilot::dashboard.partials.icon', ['name' => 'copy'])Copy</button>
                    </div>
                    <p class="hint">Put it in the secret store of whatever calls the API. If you lose it, rotate: a new one replaces it.</p>
                </div>
            @endif

            <h3>Token <span class="token-state {{ $token !== null ? 'on' : 'off' }}">{{ $token !== null ? 'Active' : 'None' }}</span></h3>
            @if ($token !== null)
                <dl class="facts">
                    <div><dt>Begins with</dt><dd><code>{{ $token['prefix'] }}…</code></dd></div>
                    <div><dt>Made</dt><dd>{{ $token['created_at'] ? \Illuminate\Support\Carbon::parse($token['created_at'])->format('Y-m-d H:i') : '—' }}{{ $token['created_by'] ? ' by '.$token['created_by'] : '' }}</dd></div>
                </dl>
            @else
                <p class="hint" style="margin: 8px 0 0">No token has been made here. @if ($env_token) The API answers to <code>LARAPILOT_API_TOKEN</code> from <code>.env</code> meanwhile. @endif</p>
            @endif

            @if ($env_token)
                <p class="hint" style="margin-top: 10px">A token is also set in <code>.env</code> as <code>LARAPILOT_API_TOKEN</code>: the API accepts it too. Remove it from <code>.env</code> once every caller uses the token made here.</p>
            @endif

            <div class="token-actions">
                <form method="post" action="{{ route('larapilot.dashboard.api-token.issue') }}" @if ($token !== null) onsubmit="return confirm('Rotate the token? The current one stops working at once; every caller needs the new one.')" @endif>
                    @csrf
                    <button type="submit" class="btn">@include('larapilot::dashboard.partials.icon', ['name' => $token !== null ? 'refresh' : 'key']){{ $token !== null ? 'Rotate token' : 'Generate token' }}</button>
                </form>
                @if ($token !== null)
                    <form method="post" action="{{ route('larapilot.dashboard.api-token.revoke') }}" onsubmit="return confirm('Revoke the token? Nothing will answer to it any more.')">
                        @csrf
                        <button type="submit" class="btn danger">@include('larapilot::dashboard.partials.icon', ['name' => 'trash'])Revoke</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="card">
            <h3>How callers use it</h3>
            <p class="hint">As a bearer token, or in the <code>X-Larapilot-Token</code> header:</p>
            <pre class="snippet"><code>curl -H "Authorization: Bearer &lt;token&gt;" {{ $example_url }}
curl -H "X-Larapilot-Token: &lt;token&gt;" {{ $example_url }}</code></pre>
            <p class="hint" style="margin-top: 14px">
                @if ($mode === 'open' && ($token !== null || $env_token))
                    A token exists, so every call needs it — on this machine (<code>APP_ENV={{ app()->environment() }}</code>) too, reads included. The API reference stays open from the sidebar; send the token there with <strong>Authorize</strong>. On a shared host a user signed in on the dashboard whose role sees the API also reads it from the browser.
                @elseif ($mode === 'open')
                    No token exists yet, so on this machine (<code>APP_ENV={{ app()->environment() }}</code>) the API {{ $api_auth ? 'is closed: the api_auth setting is ON and asks for a token that does not exist' : 'answers without one; making a token, or the api_auth setting, closes it' }}. On a shared host it stays closed until a token exists.
                @else
                    Here every call needs the token; a user signed in on the dashboard whose role sees the API reads it from the browser without one. Writes always need the token.
                @endif
                The same from the shell: <code>php artisan larapilot:api-token {show|generate|rotate|revoke}</code>.
            </p>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.querySelector(button.getAttribute('data-copy'));

            if (! field) {
                return;
            }

            field.select();

            var done = function () {
                var label = button.innerHTML;
                button.textContent = 'Copied';
                setTimeout(function () { button.innerHTML = label; }, 1600);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(field.value).then(done, function () { document.execCommand('copy'); done(); });
            } else {
                document.execCommand('copy');
                done();
            }
        });
    });
</script>
@endpush
