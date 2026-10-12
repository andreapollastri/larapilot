@extends('larapilot::auth.layout')

@section('title', 'Your code')

@section('content')
    <p class="eyebrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="3" width="12" height="18" rx="2"/><path d="M11 18h2"/></svg>
        Second step
    </p>

    <h1>Enter the code of your authenticator</h1>
    <p>Open the authenticator app on your phone and type the six digits it shows for <strong>{{ $username }}</strong>. A code is good for thirty seconds, and once.</p>

    @if ($errors->any())
        <div class="alert error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('larapilot.auth.two-factor.verify') }}">
        @csrf
        <label class="field">
            <span>Six-digit code</span>
            <input class="control otp" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="7" required autofocus>
        </label>
        <button type="submit" class="btn">Continue</button>
    </form>

    <div class="aside">
        <p>Lost the phone? An admin resets your authenticator from the Users page, or with <code>php artisan larapilot:dashboard-user totp-reset {{ $username }}</code>.</p>
    </div>
@endsection

@section('foot')
    <span>Signed in as {{ $username }}.</span>
    <form method="post" action="{{ route('larapilot.auth.logout') }}">
        @csrf
        <button type="submit" class="link-btn">Not you? Sign out</button>
    </form>
@endsection
