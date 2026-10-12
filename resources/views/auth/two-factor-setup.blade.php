@extends('larapilot::auth.layout')

@section('title', 'Set up your authenticator')

@section('body-class', 'wide')

@section('content')
    <p class="eyebrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.4 7.8 8 9 4.6-1.2 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg>
        Second factor
    </p>

    <h1>Set up your authenticator</h1>
    <p>Production asks every user of the dashboard for a second factor: a code from an authenticator app, at every sign-in. It takes a minute, once.</p>

    @include('larapilot::dashboard.partials.totp-setup', ['action' => route('larapilot.auth.two-factor.setup.confirm')])
@endsection

@section('foot')
    <span>Signed in as {{ $username }}.</span>
    <form method="post" action="{{ route('larapilot.auth.logout') }}">
        @csrf
        <button type="submit" class="link-btn">Not now — sign out</button>
    </form>
@endsection
