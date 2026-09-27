@php
    /** @var array<string, mixed> $skill */
    $origin = $skill['origin'];
    $library = \Larapilot\Services\SkillLibraryService::class;

    // A skill of the project or of Larapilot is the one its name leads to.
    // Any other says where it comes from, so the right one is read.
    $address = ['name' => $skill['folder']];

    if (! in_array($origin, [$library::CUSTOM, $library::PACKAGED], true)) {
        $address['from'] = $skill['id'];
    }

    [$chip, $tone] = match (true) {
        $origin === $library::CUSTOM && $skill['registered'] => ['Registered', 'registered'],
        $origin === $library::CUSTOM => ['Not registered', 'pending'],
        $origin === $library::PACKAGED => ['Packaged', ''],
        $origin === $library::PACKAGE => ['Package', 'package'],
        $origin === $library::BOOST => ['Boost', 'boost'],
        $origin === $library::PROJECT => ['Project', 'registered'],
        default => ['Agent only', 'pending'],
    };

    $where = match ($origin) {
        $library::PACKAGED => rtrim(rtrim(number_format($skill['bytes'] / 1024, 1, '.', ','), '0'), '.').' KB',
        $library::PACKAGE => $skill['source'],
        $library::BOOST => trim(($skill['about'] ?? 'boost').' '.($skill['version'] ?? '')),
        default => $skill['relative_path'],
    };

    $readers = array_values(array_unique(array_column($skill['installed'], 'agent')));
    $differs = in_array('differs', array_column($skill['installed'], 'state'), true);
    $shown = ($showAgents ?? false) === true;
@endphp
<a class="card skill-card" href="{{ route('larapilot.dashboard.skill', $address) }}" data-find="{{ strtolower($skill['name'].' '.($skill['title'] ?? '').' '.($skill['description'] ?? '').' '.$skill['source'].' '.($skill['about'] ?? '')) }}">
    <div class="skill-card-head">
        <div>
            <h3>{{ $skill['title'] ?? $skill['name'] }}</h3>
            <code class="trigger">{{ $skill['trigger'] }}</code>
        </div>
        <span class="skill-chip {{ $tone }}">{{ $chip }}</span>
    </div>
    <p class="about">{{ $skill['description'] ?? 'No description in the front matter of the skill.' }}</p>
    @if ($shown)
        <p class="agents-line">
            @if ($readers === [])
                <span class="dot off"></span>No agent has it
            @else
                <span class="dot {{ $differs ? 'warn' : 'on' }}"></span>{{ implode(' · ', $readers) }}{{ $differs ? ' — not the same text' : '' }}
            @endif
        </p>
    @endif
    <div class="meta">
        <span class="path">{{ $where }}</span>
        <span class="read">Read @include('larapilot::dashboard.partials.icon', ['name' => 'chevron'])</span>
    </div>
</a>
