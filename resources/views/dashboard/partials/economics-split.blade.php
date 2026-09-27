{{--
    One amount cut into the parts it is made of, to scale. The receipt beside
    it carries the names and the figures; the bar shows how large each part
    is against the others.

    $label     what the whole bar is, for a screen reader
    $segments  list of [key, label, value, amount] — value a number, amount formatted
--}}
@php
    $parts = array_values(array_filter($segments, static fn (array $segment): bool => (float) $segment['value'] > 0));
    $whole = array_sum(array_map(static fn (array $segment): float => (float) $segment['value'], $parts));
    $share = static fn (array $segment): int => $whole > 0 ? (int) round((float) $segment['value'] / $whole * 100) : 0;
    $spoken = implode(', ', array_map(
        static fn (array $segment): string => $segment['label'].' '.$segment['amount'].' ('.$share($segment).'%)',
        $parts
    ));
@endphp
@if ($parts !== [])
    <div class="split" role="img" aria-label="{{ $label }}: {{ $spoken }}">
        @foreach ($parts as $segment)
            <span
                class="split-part k-{{ $segment['key'] }}"
                style="flex-grow: {{ round((float) $segment['value'], 2) }}"
                title="{{ $segment['label'] }} — {{ $segment['amount'] }} · {{ $share($segment) }}%"
            ></span>
        @endforeach
    </div>
    <ul class="split-key" aria-hidden="true">
        @foreach ($parts as $segment)
            <li class="k-{{ $segment['key'] }}"><span class="swatch"></span>{{ $segment['label'] }} <b>{{ $share($segment) }}%</b></li>
        @endforeach
    </ul>
@endif
