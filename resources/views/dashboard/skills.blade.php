@extends('larapilot::dashboard.layout')

@section('title', 'Skills')

@push('styles')
<style>
    .skills-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }

    @media (min-width: 640px) {
        .skills-grid { grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
    }

    .skill-card {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 18px 20px;
    }

    .skill-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }

    .skill-card h3 {
        margin: 0 0 4px;
        font-size: 1rem;
    }

    .skill-card .trigger {
        padding: 0;
        background: transparent;
        color: var(--accent);
        font-size: 0.82rem;
        font-weight: 600;
    }

    .skill-card .about {
        margin: 0;
        color: var(--text-2);
        font-size: 0.875rem;
        line-height: 1.55;
    }

    .skill-card .meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: auto;
        padding-top: 12px;
        border-top: 1px solid var(--border);
        color: var(--muted);
        font-size: 0.75rem;
    }

    .skill-card .path {
        font-family: var(--mono);
        overflow-wrap: anywhere;
    }

    .skill-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 650;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
        --tone: var(--status-todo);
        background: color-mix(in srgb, var(--tone) 15%, transparent);
        color: color-mix(in srgb, var(--tone) 54%, var(--text));
    }

    .skill-chip::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--tone);
    }

    .skill-chip.registered { --tone: var(--status-done); }
    .skill-chip.pending { --tone: var(--status-progress); }
</style>
@endpush

@section('content')
    <div class="skills-page">
        <header class="page-head">
            <div>
                <h2>Custom skills</h2>
                <p class="sub">User-authored Boost skills stored in <code>.larapilot/skills/</code>. Create them with <code>/larapilot-custom-skill</code> (persisted via <code>larapilot:custom-skill-add</code>). Larapilot registers each skill into <code>.ai/skills/</code> so Boost can publish the slash command.</p>
            </div>
            @if (Route::has('larapilot.dashboard.files.browse') && app(\Larapilot\Services\ConfigService::class)->fileManagerBrowsable())
                <div class="page-actions">
                    <a class="btn ghost" href="{{ route('larapilot.dashboard.files.browse', ['root' => 'skills']) }}">@include('larapilot::dashboard.partials.icon', ['name' => 'folder'])Browse files</a>
                </div>
            @endif
        </header>

        @if (($skills ?? []) === [])
            <div class="card empty">
                <p>No custom skills yet. Run <code>/larapilot-custom-skill</code> to author one — it is saved under <code>.larapilot/skills/{name}/SKILL.md</code>.</p>
            </div>
        @else
            <div class="skills-grid">
                @foreach ($skills as $skill)
                    <article class="card skill-card">
                        <div class="skill-card-head">
                            <div>
                                <h3>{{ $skill['title'] ?? $skill['name'] }}</h3>
                                <code class="trigger">{{ $skill['trigger'] }}</code>
                            </div>
                            @if ($skill['registered'])
                                <span class="skill-chip registered">Registered</span>
                            @else
                                <span class="skill-chip pending">Not registered</span>
                            @endif
                        </div>
                        @php
                            $about = $skill['description'] ?? $skill['summary'] ?? null;
                        @endphp
                        @if ($about)
                            <p class="about">{{ $about }}</p>
                        @else
                            <p class="about">No description in the SKILL.md front matter.</p>
                        @endif
                        <div class="meta">
                            <span class="path">{{ $skill['relative_path'] }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
