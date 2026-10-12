@extends('larapilot::dashboard.layout')

@section('title', 'Set up your authenticator')

@push('styles')
<style>
    .totp-card { max-width: 640px; padding: 22px; }
    .totp-card h3 { margin: 0 0 8px; }
    .totp-steps { margin: 0 0 8px; padding-left: 1.2em; color: var(--text-2); }
    .totp-steps li { margin-bottom: 6px; }
    .totp-pair { display: grid; gap: 16px; align-items: start; margin: 14px 0 4px; }
    @media (min-width: 560px) { .totp-pair { grid-template-columns: auto minmax(0, 1fr); } }
    .totp-qr { display: grid; place-items: center; width: fit-content; padding: 10px; background: #fff; border: 1px solid var(--border); border-radius: 12px; }
    .totp-qr svg { display: block; width: 180px; height: 180px; }
    .totp-secret { min-width: 0; }
    .totp-secret p { margin: 0 0 6px; font-size: 0.86rem; color: var(--text-2); }
    .totp-secret code { display: block; padding: 8px 10px; font-size: 0.95rem; letter-spacing: 0.06em; overflow-wrap: anywhere; }
    .totp-card form { display: grid; gap: 12px; margin-top: 14px; }
    .totp-card .field { display: grid; gap: 5px; }
    .totp-card .field > span { font-size: 0.82rem; font-weight: 600; color: var(--text-2); }
    .totp-card .control { width: 100%; }
    .totp-card .control.otp { font-family: var(--mono); font-size: 1.4rem; letter-spacing: 0.35em; text-align: center; }
    .totp-card .alert { padding: 10px 12px; border-radius: 10px; background: color-mix(in srgb, var(--danger-solid) 12%, transparent); color: var(--danger-solid); font-size: 0.9rem; }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Set up your authenticator</h2>
            <p class="sub">A second factor beside the password. Once it is on, every sign-in asks for the code the app shows.</p>
        </div>
        <div class="page-actions">
            <a class="btn ghost" href="{{ route('larapilot.dashboard.account') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'back'])Back to the account</a>
        </div>
    </header>

    <section class="card totp-card">
        @include('larapilot::dashboard.partials.totp-setup', ['action' => route('larapilot.dashboard.account.two-factor.enable')])
    </section>
@endsection
