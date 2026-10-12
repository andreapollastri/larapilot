@extends('larapilot::dashboard.layout')

@section('title', 'Account')

@push('styles')
<style>
    .account-grid { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 900px) { .account-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .account-grid .card { padding: 20px; }
    .account-grid h3 { margin: 0 0 6px; font-size: 1.02rem; }
    .account-grid .hint { margin: 0 0 14px; }
    .facts { display: grid; gap: 8px; margin: 0; }
    .facts > div { display: grid; grid-template-columns: 120px minmax(0, 1fr); gap: 10px; }
    .facts dt { color: var(--muted); font-size: 0.82rem; }
    .facts dd { margin: 0; overflow-wrap: anywhere; }
    .account-form { display: grid; gap: 12px; margin-top: 12px; }
    .account-form .field { display: grid; gap: 5px; }
    .account-form .field > span { font-size: 0.82rem; font-weight: 600; color: var(--text-2); }
    .account-form .control { width: 100%; }
    .account-form .control.otp { font-family: var(--mono); font-size: 1.25rem; letter-spacing: 0.3em; text-align: center; }
    .account-form .actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .state { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
    .state.on { background: color-mix(in srgb, var(--status-done) 16%, transparent); color: var(--status-done); }
    .state.off { background: var(--surface-3); color: var(--muted); }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Your account</h2>
            <p class="sub">Who you are on this dashboard, your password, and your authenticator. The users themselves — who exists and with which role — are the admins' business, on <a href="{{ route('larapilot.dashboard.users') }}">Users</a>.</p>
        </div>
    </header>

    <div class="account-grid">
        <section class="card">
            <h3>You</h3>
            <dl class="facts">
                <div><dt>Username</dt><dd><strong>{{ $account['name'] }}</strong></dd></div>
                <div><dt>Role</dt><dd><span class="chip">{{ $account['role_label'] }}</span><br><span class="hint">{{ $account['role_description'] }}</span></dd></div>
                <div><dt>Since</dt><dd>{{ $account['created_at'] ? \Illuminate\Support\Carbon::parse($account['created_at'])->format('Y-m-d') : '—' }}</dd></div>
                <div><dt>Last sign-in</dt><dd>{{ $account['last_login_at'] ? \Illuminate\Support\Carbon::parse($account['last_login_at'])->diffForHumans() : '—' }}</dd></div>
            </dl>
        </section>

        <section class="card">
            <h3>Password</h3>
            <p class="hint">At least {{ $password_min }} characters; anything goes but your username. Changing it signs out every other session of yours.</p>
            <form class="account-form" method="post" action="{{ route('larapilot.dashboard.account.password') }}">
                @csrf
                <label class="field"><span>Current password</span><input class="control" type="password" name="current_password" required autocomplete="current-password"></label>
                <label class="field"><span>New password</span><input class="control" type="password" name="password" required minlength="{{ $password_min }}" autocomplete="new-password"></label>
                <label class="field"><span>New password, again</span><input class="control" type="password" name="password_confirmation" required minlength="{{ $password_min }}" autocomplete="new-password"></label>
                <div class="actions"><button type="submit" class="btn">Change password</button></div>
            </form>
        </section>

        <section class="card" id="two-factor">
            <h3>Authenticator <span class="state {{ $account['totp'] ? 'on' : 'off' }}">{{ $account['totp'] ? 'On' : 'Off' }}</span></h3>
            @if ($account['totp'])
                <p class="hint">
                    Every sign-in asks for the six-digit code of your app{{ $account['totp_since'] ? ', since '.\Illuminate\Support\Carbon::parse($account['totp_since'])->format('Y-m-d') : '' }}.
                    @if ($totp_mandatory)
                        Production asks it of every user: it cannot be turned off here. Lost the phone? An admin resets it from Users, and you enrol a new one at your next sign-in.
                    @else
                        Lost the phone? An admin resets it from Users.
                    @endif
                </p>
                @unless ($totp_mandatory)
                    <details>
                        <summary class="btn ghost small">Turn the authenticator off</summary>
                        <form class="account-form" method="post" action="{{ route('larapilot.dashboard.account.two-factor.disable') }}">
                            @csrf
                            <label class="field"><span>Your password</span><input class="control" type="password" name="password" required autocomplete="current-password"></label>
                            <label class="field"><span>A current code from the app</span><input class="control otp" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="7" required></label>
                            <div class="actions"><button type="submit" class="btn danger">Turn it off</button></div>
                        </form>
                    </details>
                @endunless
            @else
                <p class="hint">
                    A second factor beside the password: a six-digit code from an app on your phone, at every sign-in. Google Authenticator, Microsoft Authenticator, 1Password, Authy — any app that reads a QR code.
                    @if ($totp_mandatory)
                        Production asks it of every user.
                    @endif
                </p>
                <a class="btn" href="{{ route('larapilot.dashboard.account.two-factor') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'shield'])Set up an authenticator</a>
            @endif
        </section>
    </div>
@endsection
