## Vue Playbook _(Joe)_

Defaults for Vue code — Vite SPAs, Nuxt, Quasar — used where the frontend repository's rules and the `observed` lines of `frontend-scan` are silent. Order of authority: the repository's rules, then `observed`, then this file. Write for `framework.version`; never use an API newer than it.

### What each version adds

| Version | Available — use it where the code already does |
| --- | --- |
| Vue 2.6 | Options API; Vuex. End of life since 2023: no new dependency that needs Vue 3. |
| Vue 2.7 | Composition API and `<script setup>` backported. |
| Vue 3.0–3.2 | Composition API; `<script setup>` stable from 3.2; Teleport, Suspense. |
| Vue 3.3 | Generic components, typed `defineEmits` shorthand, `defineOptions`, `defineSlots`. |
| Vue 3.4 | `defineModel()` stable; `v-bind` same-name shorthand. |
| Vue 3.5 | Reactive props destructure, `useTemplateRef()`, `useId()`, lazy hydration, `onWatcherCleanup`. |
| Nuxt 3 | Auto-imports, `useFetch` / `useAsyncData` / `$fetch`, `server/` routes, `useState`. |
| Nuxt 4 | Code under `app/` by default; shared data across `useAsyncData` calls with the same key; stricter TypeScript. |

### Components

- `<script setup lang="ts">` when `observed` counts it; Options API files stay Options API unless a rule says to migrate. One style per component.
- Props with `defineProps<…>()` typed, emits with `defineEmits<…>()`, `defineModel()` for `v-model` on 3.4+.
- Logic shared between components goes in a composable (`useX.ts`, in the folder `observed` shows); composables return refs, not reactive objects to destructure.
- `<style scoped>` when the project scopes; design tokens and the UI kit of `libraries.ui` (Vuetify, Quasar, PrimeVue, Element Plus, Naive UI, Nuxt UI) before custom components.
- `v-for` always with a `:key` from an id; never `v-if` and `v-for` on one element.

### State and data

- Shared state in Pinia (`libraries.state`) in the store style `observed` counts (setup or option stores); Vuex only in a project that has it. Never introduce another one.
- Server state through TanStack Vue Query when present, Nuxt `useFetch` / `useAsyncData` in Nuxt, or the project's API module — never fetched in a component's `onMounted` when one of these exists.
- A generated client (`api_client.generated`) is the only way to call the API. Hand-written calls go through the project's one HTTP instance (axios, `ofetch`, `$fetch`) with the base URL from env, typed from the OpenAPI schemas.
- Laravel API: Sanctum SPA auth needs credentials included and a call to `/sanctum/csrf-cookie` first (Nuxt: `nuxt-auth-sanctum` when present); token auth sends `Authorization: Bearer`. Map 422 `errors` onto the fields of the form.

### Forms, routing, i18n

- Forms with the library of `libraries.forms` (VeeValidate, Vuelidate) and its schema (Zod, Yup, Valibot). Every field has a label and an error tied with `aria-describedby`.
- Vue Router with lazy route components (`() => import(...)`); Nuxt file-based routing and `definePageMeta` for middleware.
- i18n through `vue-i18n` or `@nuxtjs/i18n` keys when the project translates.

### Nuxt specifics

- Rely on auto-imports the way the code does; do not add explicit imports of auto-imported APIs in a project that never writes them.
- Browser-only code inside `onMounted`, `<ClientOnly>`, or `import.meta.client`; secrets only in `runtimeConfig` server keys.

### Accessibility and tests

- Semantic elements before ARIA; keyboard support for every interactive element; WCAG 2.2 AA per `ux-1.md`.
- Vitest with `@vue/test-utils` or `@testing-library/vue` (`@nuxt/test-utils` in Nuxt): mount, interact, assert what the user sees. Playwright or Cypress for the journeys the task names.
