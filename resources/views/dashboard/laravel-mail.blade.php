@extends('larapilot::dashboard.layout')

@section('title', 'Mail · Laravel')

@push('styles')
@include('larapilot::dashboard.partials.laravel-styles')
@endpush

@section('content')
    @php
        $people = static fn (array $addresses): string => implode(', ', array_map(static fn (array $address): string => (string) ($address['address'] ?? ''), $addresses));
        $goesNowhere = in_array($transport, ['log', 'array'], true);
    @endphp

    <header class="page-head">
        <div>
            <h2>Laravel</h2>
            <p class="sub">The mail the application sent, the newest first, as it left: who it went to, the subject, and the message itself. Kept whatever the mailer — <code>log</code> and <code>array</code> included — and never sent again from here.</p>
        </div>
        @if ($records !== [])
            <form class="page-actions" method="post" action="{{ route('larapilot.dashboard.laravel.mail.clear') }}">
                @csrf
                <button type="submit" class="btn ghost">@include('larapilot::dashboard.partials.icon', ['name' => 'trash'])Forget them all</button>
            </form>
        @endif
    </header>

    @include('larapilot::dashboard.partials.laravel-tabs', ['current' => 'mail'])

    <div class="lv-bar">
        <span @class(['chip', 'live' => $recording, 'stale' => ! $recording])>@include('larapilot::dashboard.partials.icon', ['name' => 'mail']){{ $recording ? 'Every mail sent is kept' : 'Mail is not kept here' }}</span>
        <span class="chip">Mailer <code>{{ $mailer !== '' ? $mailer : '—' }}</code>@if ($transport !== '' && $transport !== $mailer) <code>{{ $transport }}</code>@endif</span>
        <span class="chip">The last {{ number_format($keep) }} in <code>{{ $directory }}</code></span>
    </div>

    @if (! $recording)
        <div class="flash flash--warn" role="status">
            @if (app()->environment(['local', 'development']))
                <strong>Keeping the mail is switched off.</strong>
                <span>Remove <code>LARAPILOT_LARAVEL_VIEWER_MAIL=false</code> from <code>.env</code> to keep what the application sends.</span>
            @else
                <strong>By default the mail is kept only on a developer's own machine.</strong>
                <span>A mail carries reset links and personal data, and everyone who signs in to this dashboard would read it. <code>LARAPILOT_LARAVEL_VIEWER_MAIL=true</code> in <code>.env</code> keeps it in <code>{{ app()->environment() }}</code> too.</span>
            @endif
        </div>
    @endif

    <section class="card lv-list" aria-label="Mail">
        @forelse ($records as $record)
            @php $sent = \Illuminate\Support\Carbon::parse($record['at'] ?? 'now'); @endphp
            <a class="lv-item" href="{{ route('larapilot.dashboard.laravel.mail.message', ['id' => $record['id']]) }}">
                <span class="lv-item-title">{{ ($record['subject'] ?? '') !== '' ? $record['subject'] : '(no subject)' }}</span>
                <span class="lv-item-when" title="{{ $sent->format('Y-m-d H:i:s') }}">{{ $sent->diffForHumans() }}</span>
                <span class="lv-item-meta">
                    <span>To {{ $people($record['to'] ?? []) ?: '—' }}</span>
                    @if (($record['source'] ?? null) !== null)
                        <code>{{ class_basename($record['source']) }}</code>
                    @endif
                    @if (($record['attachments'] ?? []) !== [])
                        <span>{{ count($record['attachments']) }} {{ count($record['attachments']) === 1 ? 'attachment' : 'attachments' }}</span>
                    @endif
                    @if (($record['context'] ?? '') !== '')
                        <span>{{ $record['context'] }}</span>
                    @endif
                </span>
            </a>
        @empty
            <div class="empty">
                @if ($recording)
                    <p>No mail yet. The next one the application sends shows up here{{ $goesNowhere ? ' — with the '.$transport.' mailer it reaches nobody else' : '' }}.</p>
                @else
                    <p>No mail was kept.</p>
                @endif
            </div>
        @endforelse
    </section>
@endsection
