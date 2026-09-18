@extends('larapilot::dashboard.layout')

@section('title', 'Skills')

@push('styles')
<style>
    body .shell:has(.skills-page) {
        max-width: none;
        padding-left: max(20px, 4vw);
        padding-right: max(20px, 4vw);
    }

    .skills-page .panel {
        padding: 24px 28px;
    }

    .skills-page h2 {
        margin: 0 0 6px;
        font-size: 1.15rem;
    }

    .skills-page .sub {
        margin: 0 0 24px;
        color: var(--muted);
        font-size: 0.9rem;
        max-width: 72ch;
    }

    .skills-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 14px;
    }

    .skill-card {
        padding: 16px 18px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: color-mix(in srgb, var(--surface) 92%, var(--bg));
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .skill-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }

    .skill-card h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
    }

    .skill-card .trigger {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--accent);
    }

    .skill-card .about {
        margin: 0;
        color: var(--muted);
        font-size: 0.875rem;
        line-height: 1.55;
    }

    .skill-card .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        margin-top: auto;
        font-size: 0.75rem;
        color: var(--muted);
    }

    .skill-card .path {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        word-break: break-all;
    }

    .skill-chip {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 999px;
        border: 1px solid var(--border);
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .skill-chip.registered {
        border-color: var(--status-done);
        color: var(--status-done);
        background: color-mix(in srgb, var(--status-done) 14%, transparent);
    }

    .skill-chip.pending {
        border-color: var(--status-progress);
        color: var(--status-progress);
        background: color-mix(in srgb, var(--status-progress) 14%, transparent);
    }
</style>
@endpush

@section('content')
    <div class="skills-page">
        <section class="card panel">
            <h2>Custom skills</h2>
            <p class="sub">User-authored Boost skills stored in <code>.larapilot/skills/</code>. Create them with <code>/larapilot-custom-skill</code> (persisted via <code>larapilot:custom-skill-add</code>). Larapilot registers each skill into <code>.ai/skills/</code> so Boost can publish the slash command.</p>

            @if (($skills ?? []) === [])
                <div class="empty" style="padding: 24px;">No custom skills yet. Run <code>/larapilot-custom-skill</code> to author one — it is saved under <code>.larapilot/skills/{name}/SKILL.md</code>.</div>
            @else
                <div class="skills-grid">
                    @foreach ($skills as $skill)
                        <article class="skill-card">
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
        </section>
    </div>
@endsection
