@extends('larapilot::auth.layout')

@section('title', 'Sign in')

@section('content')
    <p class="eyebrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        Sign in
    </p>

    <h1>Sign in to the dashboard</h1>
    <p>
        This environment (<code>APP_ENV={{ app()->environment() }}</code>) asks for a user.
        @if ($totp_mandatory)
            Production asks every user for an authenticator app as well: you set it up right after your first sign-in.
        @endif
    </p>

    @if (session('larapilot_success'))
        <div class="alert ok" role="status">{{ session('larapilot_success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('larapilot.auth.login.attempt') }}">
        @csrf
        <label class="field">
            <span>Username</span>
            <input class="control" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="255">
        </label>
        <label class="field">
            <span>Password</span>
            <input class="control" type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn">Sign in</button>
    </form>

    <div class="aside">
        <p>There is no sign-up and no password reset: an admin creates users and sets passwords on the <strong>Users</strong> page of the dashboard, or with <code>php artisan larapilot:dashboard-user</code>.</p>
    </div>
@endsection
