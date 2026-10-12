@extends('larapilot::dashboard.layout')

@section('title', 'Users')

@push('styles')
<style>
    .users-layout { display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1100px) { .users-layout { grid-template-columns: minmax(0, 1fr) 340px; } }
    .users-mode { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; margin-bottom: 16px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface); color: var(--text-2); font-size: 0.88rem; }
    .users-mode .icon { flex: none; width: 18px; height: 18px; margin-top: 2px; color: var(--accent); }
    .users-mode code { white-space: nowrap; }
    .user-list { padding: 0; overflow: hidden; }
    .user-head, .user-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "name role" "actions actions"; gap: 10px 14px; align-items: center; padding: 14px 16px; }
    .user-head { grid-template-areas: "name role"; }
    .user-head span:nth-child(3) { display: none; }
    .user-name { grid-area: name; }
    .user-role { grid-area: role; justify-content: flex-end; }
    .user-actions { grid-area: actions; justify-content: flex-start !important; }
    .user-head { border-bottom: 1px solid var(--border); color: var(--muted); font-size: 0.7rem; font-weight: 650; letter-spacing: 0.08em; text-transform: uppercase; }
    .user-row { border-top: 1px solid var(--border); }
    .user-head + .user-row { border-top: 0; }
    .user-row.is-self { background: color-mix(in srgb, var(--accent) 5%, transparent); }
    .user-name { min-width: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; }
    .user-name strong { overflow-wrap: anywhere; }
    .user-meta { display: block; color: var(--muted); font-size: 0.78rem; }
    .user-role { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .user-role .inline-form { flex-wrap: nowrap; align-items: center; }
    .user-role select { min-height: 34px; min-width: 150px; padding: 4px 28px 4px 10px; font-size: 0.85rem; }
    .user-role .hint { max-width: 220px; }
    .user-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
    .user-more { grid-column: 1 / -1; grid-row: auto; display: grid; gap: 10px; padding: 12px 14px; border-radius: var(--radius-sm); background: var(--surface-3); }
    .user-more summary { cursor: pointer; }
    .inline-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
    .inline-form .field { display: grid; gap: 4px; min-width: 0; flex: 1 1 180px; }
    .inline-form .field > span { font-size: 0.78rem; font-weight: 600; color: var(--text-2); }
    .inline-form .control { width: 100%; min-height: 36px; }
    @media (max-width: 720px) { .user-head { display: none; } .user-row { grid-template-columns: minmax(0, 1fr); grid-template-areas: "name" "role" "actions"; } .user-role { justify-content: flex-start; } }
    .new-user { display: grid; gap: 12px; }
    .new-user .field { display: grid; gap: 5px; }
    .new-user .field > span { font-size: 0.82rem; font-weight: 600; color: var(--text-2); }
    .new-user .control { width: 100%; }
    .roles { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .roles li { padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); }
    .roles li strong { display: block; margin-bottom: 2px; }
    .roles li p { margin: 0 0 6px; color: var(--text-2); font-size: 0.84rem; }
    .roles .areas { display: flex; flex-wrap: wrap; gap: 4px; }
    .roles .areas span { padding: 1px 7px; border-radius: 999px; background: var(--surface-3); color: var(--muted); font-size: 0.72rem; }
    .state { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 999px; font-size: 0.74rem; font-weight: 600; }
    .state.on { background: color-mix(in srgb, var(--status-done) 16%, transparent); color: var(--status-done); }
    .state.off { background: var(--surface-3); color: var(--muted); }
    .side-card { padding: 18px 20px; }
    .side-card h3 { margin: 0 0 6px; font-size: 1rem; }
    .side-card .hint { margin: 0 0 12px; }
    .side-card pre { margin: 0; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface-3); white-space: pre-wrap; overflow-wrap: anywhere; font-size: 0.78rem; line-height: 1.6; }
    .side-card pre code { padding: 0; background: none; font-size: inherit; }
</style>
@endpush

@section('content')
    <header class="page-head">
        <div>
            <h2>Users</h2>
            <p class="sub">Who signs in to this dashboard and with which role. Passwords are kept hashed in <code>{{ $path }}</code>, out of Git; there is no sign-up and no password reset — you set them here. You cannot remove or rename yourself, nor change your own role: another admin can.</p>
        </div>
    </header>

    <div class="users-mode" role="note">
        @include('larapilot::dashboard.partials.icon', ['name' => $mode === 'open' ? 'info' : 'lock'])
        <div>
            <strong>{{ $mode === 'open' ? 'Open here' : ($mode === 'production' ? 'Production' : 'Sign-in on') }}</strong> · {{ $mode_label }}
            @if ($mode === 'open')
                These users count on every other environment (staging, production with <code>LARAPILOT_FORCE_IN_PROD=true</code>).
            @endif
        </div>
    </div>

    <div class="users-layout">
        <div>
            <section class="card user-list" aria-label="Users">
                <div class="user-head"><span>User</span><span>Role</span><span>Actions</span></div>
                @forelse ($users as $user)
                    <div class="user-row @if ($user['self']) is-self @endif">
                        <div class="user-name">
                            <strong>{{ $user['name'] }}</strong>
                            @if ($user['self'])<span class="chip current">You</span>@endif
                            <span class="state {{ $user['totp'] ? 'on' : 'off' }}" title="{{ $user['totp'] ? 'Signs in with an authenticator' : 'Password alone' }}">@include('larapilot::dashboard.partials.icon', ['name' => 'shield']){{ $user['totp'] ? '2FA on' : '2FA off' }}</span>
                            <span class="user-meta">Last sign-in {{ $user['last_login_at'] ? \Illuminate\Support\Carbon::parse($user['last_login_at'])->diffForHumans() : 'never' }}{{ $user['created_at'] ? ' · since '.\Illuminate\Support\Carbon::parse($user['created_at'])->format('Y-m-d') : '' }}</span>
                        </div>
                        <div class="user-role">
                            @if ($user['self'])
                                <span class="chip">{{ $user['role_label'] }}</span>
                                <span class="hint">Another admin changes your role.</span>
                            @else
                                <form class="inline-form" method="post" action="{{ route('larapilot.dashboard.users.role', ['user' => $user['name']]) }}">
                                    @csrf
                                    <select class="control" name="role" aria-label="Role of {{ $user['name'] }}">
                                        @foreach ($roles as $key => $role)
                                            <option value="{{ $key }}" @selected($key === $user['role'])>{{ $role['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn ghost small">Save</button>
                                </form>
                            @endif
                        </div>
                        <div class="user-actions">
                            <button type="button" class="btn ghost small" data-user-toggle="more-{{ $loop->index }}" aria-expanded="false" aria-controls="more-{{ $loop->index }}">@include('larapilot::dashboard.partials.icon', ['name' => 'key'])Password</button>
                            @unless ($user['self'])
                                <button type="button" class="btn ghost small" data-user-toggle="rename-{{ $loop->index }}" aria-expanded="false" aria-controls="rename-{{ $loop->index }}">@include('larapilot::dashboard.partials.icon', ['name' => 'pencil'])Rename</button>
                                @if ($user['totp'])
                                    <form method="post" action="{{ route('larapilot.dashboard.users.two-factor.reset', ['user' => $user['name']]) }}" onsubmit="return confirm('Reset the authenticator of {{ addslashes($user['name']) }}? {{ $totp_mandatory ? 'They enrol a new one at their next sign-in.' : 'They sign in with the password alone until they enrol a new one.' }}')">
                                        @csrf
                                        <button type="submit" class="btn ghost small">@include('larapilot::dashboard.partials.icon', ['name' => 'refresh'])Reset 2FA</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('larapilot.dashboard.users.delete', ['user' => $user['name']]) }}" onsubmit="return confirm('Remove {{ addslashes($user['name']) }}? Their open sessions are signed out.')">
                                    @csrf
                                    <button type="submit" class="btn danger small">@include('larapilot::dashboard.partials.icon', ['name' => 'trash'])Remove</button>
                                </form>
                            @endunless
                        </div>
                        <div class="user-more" id="more-{{ $loop->index }}" hidden>
                            <form class="inline-form" method="post" action="{{ route('larapilot.dashboard.users.password', ['user' => $user['name']]) }}">
                                @csrf
                                <label class="field"><span>New password for {{ $user['name'] }}</span><input class="control" type="password" name="password" required minlength="{{ $password_min }}" autocomplete="new-password"></label>
                                <label class="field"><span>Again</span><input class="control" type="password" name="password_confirmation" required minlength="{{ $password_min }}" autocomplete="new-password"></label>
                                <button type="submit" class="btn small">Set password</button>
                            </form>
                            <span class="hint">At least {{ $password_min }} characters. Their open sessions are signed out.</span>
                        </div>
                        @unless ($user['self'])
                            <div class="user-more" id="rename-{{ $loop->index }}" hidden>
                                <form class="inline-form" method="post" action="{{ route('larapilot.dashboard.users.rename', ['user' => $user['name']]) }}">
                                    @csrf
                                    <label class="field"><span>New name for {{ $user['name'] }}</span><input class="control" type="text" name="name" required maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._@+\-]*" autocomplete="off" autocapitalize="none" spellcheck="false"></label>
                                    <button type="submit" class="btn small">Rename</button>
                                </form>
                                <span class="hint">Letters, digits, and <code>. _ @ + -</code>. They sign in with the new name from now on.</span>
                            </div>
                        @endunless
                    </div>
                @empty
                    <div class="empty" style="padding: 28px 16px; text-align: center; color: var(--muted)">
                        No user yet. @if ($mode !== 'open') The dashboard stays closed on every other environment until the first admin exists. @endif
                    </div>
                @endforelse
            </section>
        </div>

        <aside>
            <section class="card side-card">
                <h3>Add a user</h3>
                <p class="hint">They sign in with this name and password. {{ $totp_mandatory ? 'Production asks them for an authenticator at their first sign-in.' : 'They can add an authenticator from their account.' }}</p>
                <form class="new-user" method="post" action="{{ route('larapilot.dashboard.users.store') }}">
                    @csrf
                    <label class="field"><span>Username</span><input class="control" type="text" name="username" value="{{ old('username') }}" required maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9._@+\-]*" autocomplete="off" autocapitalize="none" spellcheck="false"></label>
                    <label class="field"><span>Password</span><input class="control" type="password" name="password" required minlength="{{ $password_min }}" autocomplete="new-password"></label>
                    <label class="field"><span>Password, again</span><input class="control" type="password" name="password_confirmation" required minlength="{{ $password_min }}" autocomplete="new-password"></label>
                    <label class="field"><span>Role</span>
                        <select class="control" name="role">
                            @foreach ($roles as $key => $role)
                                <option value="{{ $key }}" @selected(old('role', 'ext') === $key)>{{ $role['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn">@include('larapilot::dashboard.partials.icon', ['name' => 'plus'])Create user</button>
                </form>
            </section>

            <section class="card side-card" style="margin-top: 16px">
                <h3>Roles</h3>
                <ul class="roles">
                    @foreach ($roles as $key => $role)
                        <li>
                            <strong>{{ $role['label'] }} <code>{{ $key }}</code></strong>
                            <p>{{ $role['description'] }}</p>
                            <div class="areas">@foreach ($role['areas'] as $area)<span>{{ $area }}</span>@endforeach</div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="card side-card" style="margin-top: 16px">
                <h3>From the shell</h3>
                <p class="hint">The same, on a server with no admin yet:</p>
                <pre><code>php artisan larapilot:dashboard-user add &lt;name&gt; --role=admin
php artisan larapilot:dashboard-user role &lt;name&gt; dev
php artisan larapilot:dashboard-user password &lt;name&gt;
php artisan larapilot:dashboard-user totp-reset &lt;name&gt;
php artisan larapilot:dashboard-user remove &lt;name&gt;</code></pre>
            </section>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-user-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.getElementById(button.getAttribute('data-user-toggle'));

            if (! panel) {
                return;
            }

            var open = panel.hasAttribute('hidden');
            panel.toggleAttribute('hidden', ! open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                var field = panel.querySelector('input');
                field && field.focus();
            }
        });
    });
</script>
@endpush
