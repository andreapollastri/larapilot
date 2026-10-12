{{--
    The product roadmap, as the outside may read it: versions and story
    titles, nothing of how the team works. The dashboard page and the
    public link both render this.
--}}
<div class="roadmap-lanes">
    @foreach ($lanes as $lane)
        <section class="roadmap-lane" aria-labelledby="lane-{{ $lane['key'] }}">
            <header class="roadmap-lane-head">
                <h3 id="lane-{{ $lane['key'] }}">{{ $lane['label'] }}</h3>
                <p class="hint">{{ $lane['hint'] }}</p>
            </header>
            @if ($lane['versions'] === [])
                <p class="roadmap-empty">Nothing here yet.</p>
            @else
                @foreach ($lane['versions'] as $version)
                    <article class="card roadmap-version">
                        <header class="roadmap-version-head">
                            <div>
                                <p class="roadmap-label">{{ $version['label'] }}</p>
                                @if ($version['title'] !== '')
                                    <h4>{{ $version['title'] }}</h4>
                                @endif
                                @if ($version['summary'])
                                    <p class="hint">{{ $version['summary'] }}</p>
                                @endif
                            </div>
                            <div class="roadmap-meta">
                                <span class="chip {{ $version['status'] === 'shipped' ? 'live' : ($version['status'] === 'in_progress' ? 'current' : '') }}">{{ $version['status_label'] }}</span>
                                @if (is_array($version['target'] ?? null))
                                    <span class="hint">{{ $version['target']['kind'] === 'shipped' ? 'Shipped' : 'Target' }} {{ $version['target']['text'] }}</span>
                                @endif
                            </div>
                        </header>
                        @if ($version['groups'] === [])
                            <p class="hint">No stories listed for this version.</p>
                        @else
                            @foreach ($version['groups'] as $group)
                                @if ($group['title'])
                                    <h5 class="roadmap-group">{{ $group['title'] }}</h5>
                                @endif
                                <ul class="roadmap-items">
                                    @foreach ($group['items'] as $item)
                                        <li>
                                            <strong>{{ $item['title'] }}</strong>
                                            @if ($item['summary'])
                                                <span class="hint">{{ $item['summary'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endforeach
                        @endif
                        @if (! empty($team) && ($version['hidden'] ?? 0) > 0)
                            <p class="hint">{{ $version['hidden'] }} {{ $version['hidden'] === 1 ? 'story is' : 'stories are' }} hidden from the public page.</p>
                        @endif
                    </article>
                @endforeach
            @endif
        </section>
    @endforeach
</div>
