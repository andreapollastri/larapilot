@extends('larapilot::dashboard.layout')

@section('title', 'Not for your role')

@section('content')
    <header class="page-head">
        <div>
            <h2>This page is for another role</h2>
            <p class="sub">You are signed in as <strong>{{ \Larapilot\Services\Auth\AccessPolicy::roleLabel($role) }}</strong>{{ $area !== '' && isset(\Larapilot\Services\Auth\AccessPolicy::areas()[$area]) ? ', and '.\Larapilot\Services\Auth\AccessPolicy::areas()[$area]['label'].' is not among the pages of that role' : ', and this page is not among the pages of that role' }}. An admin can change roles on the Users page.</p>
        </div>
        <div class="page-actions">
            <a class="btn" href="{{ route('larapilot.dashboard.index') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'board'])Back to the board</a>
        </div>
    </header>
@endsection
