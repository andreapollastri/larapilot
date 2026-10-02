@extends('larapilot::dashboard.layout')

@section('title', (($message['subject'] ?? '') !== '' ? $message['subject'] : 'Mail').' · Laravel')

@push('styles')
@include('larapilot::dashboard.partials.laravel-styles')
@endpush

@section('content')
    @php
        $people = static fn (array $addresses): string => implode(', ', array_map(
            static fn (array $address): string => ($address['name'] ?? '') !== '' ? $address['name'].' <'.$address['address'].'>' : (string) ($address['address'] ?? ''),
            $addresses,
        ));
        $sent = \Illuminate\Support\Carbon::parse($message['at'] ?? 'now');
        $html = $message['html_body'];
        $text = $message['text_body'];

        // A link opens in a tab of its own: inside the frame the page it
        // leads to would run with no script and no session.
        if ($html !== null) {
            $base = '<base target="_blank">';
            $framed = preg_replace('/<head\b[^>]*>/i', '$0'.$base, $html, 1, $placed);
            $html = is_string($framed) && $placed === 1 ? $framed : $base.$html;
        }

        $formatBytes = static fn (int $bytes): string => app(\Larapilot\Services\FileManagerService::class)->formatBytes($bytes);
    @endphp

    <header class="page-head">
        <div>
            <h2>{{ ($message['subject'] ?? '') !== '' ? $message['subject'] : '(no subject)' }}</h2>
            <p class="sub">Sent {{ $sent->diffForHumans() }} — {{ $sent->format('D j M Y, H:i:s') }}.</p>
        </div>
        <div class="page-actions">
            <a class="btn ghost" href="{{ route('larapilot.dashboard.laravel.mail') }}">@include('larapilot::dashboard.partials.icon', ['name' => 'back'])All mail</a>
        </div>
    </header>

    @include('larapilot::dashboard.partials.laravel-tabs', ['current' => 'mail'])

    <section class="card panel" aria-label="Headers">
        <dl class="lv-mail-facts">
            <div><dt>From</dt><dd>{{ $people($message['from'] ?? []) ?: '—' }}</dd></div>
            <div><dt>To</dt><dd>{{ $people($message['to'] ?? []) ?: '—' }}</dd></div>
            @foreach (['cc' => 'Cc', 'bcc' => 'Bcc', 'reply_to' => 'Reply to'] as $key => $label)
                @if (($message[$key] ?? []) !== [])
                    <div><dt>{{ $label }}</dt><dd>{{ $people($message[$key]) }}</dd></div>
                @endif
            @endforeach
            <div><dt>Mailer</dt><dd><code>{{ $message['mailer'] ?? '—' }}</code>@if (($message['transport'] ?? null) !== null && $message['transport'] !== ($message['mailer'] ?? null)) <span class="lv-tag">{{ $message['transport'] }}</span>@endif{{ ($message['queued'] ?? false) ? ' · queued' : '' }}</dd></div>
            @if (($message['source'] ?? null) !== null)
                <div><dt>Built by</dt><dd><code>{{ $message['source'] }}</code></dd></div>
            @endif
            @if (($message['context'] ?? '') !== '')
                <div><dt>Sent during</dt><dd><code>{{ $message['context'] }}</code></dd></div>
            @endif
            @if (($message['attachments'] ?? []) !== [])
                <div><dt>Attachments</dt><dd>
                    @foreach ($message['attachments'] as $attachment)
                        <span class="lv-tag" title="{{ $attachment['type'] ?? '' }}">{{ $attachment['name'] ?? 'attachment' }} · {{ $formatBytes((int) ($attachment['size'] ?? 0)) }}</span>
                    @endforeach
                    <span class="hint" style="display: block; margin-top: 6px">The names are kept, not the files.</span>
                </dd></div>
            @endif
        </dl>
    </section>

    @if ($message['cut'] ?? false)
        <div class="flash flash--warn" role="status" style="margin-top: 14px"><strong>The message was longer than what is kept: its end is missing.</strong></div>
    @endif

    @if ($html !== null)
        <section class="card lv-panel" aria-labelledby="lv-mail-html" style="margin-top: 14px">
            <div class="lv-panel-head">
                <h3 id="lv-mail-html">Message</h3>
                <p class="hint">Shown with scripts off; a link opens in a new tab.</p>
            </div>
            <iframe class="lv-mail-frame" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" title="The message as HTML" srcdoc="{{ $html }}"></iframe>
        </section>
    @endif

    @if ($text !== null)
        <section class="card lv-panel" aria-labelledby="lv-mail-text" style="margin-top: 14px">
            <div class="lv-panel-head">
                <h3 id="lv-mail-text">{{ $html !== null ? 'Plain text' : 'Message' }}</h3>
                @if ($html !== null)
                    <p class="hint">What a client that shows no HTML reads.</p>
                @endif
            </div>
            <pre class="lv-mail-text">{{ $text }}</pre>
        </section>
    @endif

    @if ($html === null && $text === null)
        <section class="card" style="margin-top: 14px">
            <div class="empty"><p>The mail had no body.</p></div>
        </section>
    @endif
@endsection
