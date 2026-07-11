# Laravel Starter Kits — source index

Visual reference derived from the **official** Laravel starter kit repositories and docs. Use this file to know which upstream kit owns which pattern.

| Variant | Repository | Component library | CSS source |
| --- | --- | --- | --- |
| **React** | [laravel/react-starter-kit](https://github.com/laravel/react-starter-kit) | shadcn/ui + Inertia 2 | `resources/css/app.css` (`:root` / `.dark` oklch tokens) |
| **Vue** | [laravel/vue-starter-kit](https://github.com/laravel/vue-starter-kit) | shadcn-vue + Inertia 2 | Same shadcn token set as React |
| **Svelte** | [laravel/svelte-starter-kit](https://github.com/laravel/svelte-starter-kit) | shadcn-svelte + Inertia 2 | Same shadcn token set as React |
| **Livewire** | [laravel/livewire-starter-kit](https://github.com/laravel/livewire-starter-kit) | Flux UI + Livewire 4 | `resources/css/app.css` + `vendor/livewire/flux/dist/flux.css` |

## Docs

- Overview: [laravel.com/starter-kits](https://laravel.com/starter-kits)
- Customization: [laravel.com/docs/starter-kits](https://laravel.com/docs/starter-kits)

## Mockup mapping

| Packaged HTML | Starter kit concept |
| --- | --- |
| `dashboard.html` | `dashboard` route — sidebar layout + placeholder content area |
| `dashboard-dark.html` | Dark mode (`class="dark"` on `<html>`) |
| `header-layout.html` | `app-header-layout` variant (top nav instead of sidebar) |
| `login.html` | Fortify login — simple centered card auth layout |
| `auth-split.html` | Split auth layout (brand panel + form) |
| `settings.html` | Profile / password / appearance settings pages |
| `components.html` | shadcn/Flux primitives — buttons, inputs, badges, cards |

## Token notes

- **Inertia kits (React, Vue, Svelte)** share the same shadcn CSS variables in `app.css` — `tokens.css` in this folder mirrors those values.
- **Livewire** uses Flux on a zinc palette; mockups use the same shadcn sidebar tokens for visual parity with the Inertia kits (both ship a light sidebar + neutral primary by default).
- Font: **Instrument Sans** (all official kits).

When the PRD records a specific variant, Elise still uses this folder — Alex maps screens to the matching kit's pages/components at implementation time.
