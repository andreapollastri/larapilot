## Svelte Playbook _(Joe)_

Defaults for Svelte and SvelteKit code, used where the frontend repository's rules and the `observed` lines of `frontend-scan` are silent. Order of authority: the repository's rules, then `observed`, then this file. Write for `framework.version`; never use an API newer than it.

### What each version adds

| Version | Available — use it where the code already does |
| --- | --- |
| Svelte 3–4 | `export let` props, `$:` reactive statements, stores with `$store`, slots, `on:event`, `createEventDispatcher`. |
| Svelte 5 | Runes: `$state`, `$derived`, `$effect`, `$props`, `$bindable`; snippets and `{@render}` replace slots; event attributes (`onclick`) replace `on:click`; callback props replace `createEventDispatcher`; `.svelte.ts` modules hold shared reactive state. |
| SvelteKit 1–2 | `+page.svelte` / `+page.ts` / `+page.server.ts` / `+layout`, `load`, form actions, `hooks.server.ts`. |
| SvelteKit 2.12+ | `$app/state` replaces `$app/stores`. |

### Components

- Svelte 5 projects whose `observed` shows runes write runes only; a project still on `export let` stays on it until a rule says to migrate. Never mix both styles in one component.
- Props typed (`let { a, b }: Props = $props()` or `export let a: string`); events as callback props in Svelte 5.
- Derive with `$derived` (or `$:`) — never copy props into state; `$effect` only to synchronise with something outside Svelte.
- `{#each}` with a keyed id (`(item.id)`).
- Styles scoped in the component, design tokens and the UI kit of `libraries.ui` (Skeleton, Flowbite Svelte, Bits UI, shadcn-svelte) before custom components.

### State and data

- Shared state in `.svelte.ts` modules with runes, or stores in Svelte 4 code — as the project does.
- SvelteKit loads data in `load` (`+page.ts` for public data, `+page.server.ts` for anything with secrets or the session) and mutates through form actions with progressive enhancement (`use:enhance`). No fetching in `onMount` when a `load` can do it.
- A generated client (`api_client.generated`) is the only way to call the API. Hand-written calls use the `fetch` SvelteKit passes to `load` (it forwards cookies on the server), typed from the OpenAPI schemas.
- Laravel API: Sanctum SPA auth needs credentials included and `/sanctum/csrf-cookie` first; with SvelteKit server routes, keep the token on the server (`hooks.server.ts`, `locals`). Map 422 `errors` onto the fields of the form.

### Accessibility and tests

- Svelte's compiler a11y warnings are bugs, not noise. Semantic elements before ARIA; WCAG 2.2 AA per `ux-1.md`.
- Vitest with `@testing-library/svelte`; Playwright for the journeys the task names.
