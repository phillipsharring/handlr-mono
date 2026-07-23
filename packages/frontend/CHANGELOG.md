# Changelog

## 0.11.0

Declarative HTMX behavior layer (ADR 0003) — fills the gaps HTMX leaves (click
actions, post-response branching, logout) so pages stop hand-rolling
`document.addEventListener`. **Purely additive** — existing inline scripts keep
working unchanged.

### Added

- **Event delegation core** (`core/delegation.js`) — one delegated body-level
  listener per event type (boosted-nav-safe, no listener accumulation). Exports
  `onClick(selector, fn)`, `onHtmx(eventType, selector, fn)` (matches the requesting
  `detail.elt`), and the generic `onEvent(eventType, selector, fn)`.
- **Named action registry** (`core/actions.js`) — `data-action="name"` runs the
  handler registered under `name`. Exports `registerAction` / `onAction`, `getAction`,
  `hasAction`, `runAction`, `initActions`. Built-in actions: **`toggle`** (`data-toggle="#sel"`
  or `data-action="toggle" data-target="#sel"`) and **`copy`** (copies `data-copy`/text,
  fires `handlr:copied`; supersedes `initCopyIdHandler`). The registry is framework-internal
  — not on `window.App`.
- **Form-response branching** (`core/form-response.js`) — `data-on-success` /
  `data-on-error` on the requesting element decide the post-response UI. Verbs:
  `redirect` (→ `meta.redirect`, fallback `data-redirect-url`), `reveal:#sel` (hide the
  form, reveal the target), `toast:Message`, `action:name`. Imperative escape hatches
  `onFormSuccess` / `onFormError` hand back eagerly-parsed JSON (`(data, form, xhr)`).
- **Auth-flow preset** (`core/auth-flows.js`) — built-in **`logout`** action (GET the
  logout endpoint, then hard-navigate home; best-effort, `preventDefault`s) + `initAuthFlows`.
  Login/signup/forgot/reset need no new code — they use `data-on-success="redirect"` /
  `reveal:#sel`.
- `./init` now wires all of the above via `initActions()`, `initFormResponses()`, and
  `initAuthFlows()`. All new modules are **side-effect-free on import** (they attach
  listeners only when their `initX()` runs) — usable à la carte, and the barrel stays clean.

## 0.2.9

### Fixed
- `checkAdminPermissions()` (auth-state.js): registered admin prefixes ending in `/` failed to match when the URL was the prefix root without its trailing slash (e.g. `/admin` for prefix `/admin/`). The opacity gate on `<main id="app">` was never cleared, leaving the page area transparent on the index URL. Pathname is now normalized with a trailing slash before the `startsWith` check.

## 0.2.8 addendum

### Handlr Build
- Build mechanics now live in @phillipsharring/handlr-build  - separate package, no impact on runtime

## 0.2.0

### Added
- **Lifecycle hooks**  - central hook registry for app and page scripts to register callbacks at key points in the page lifecycle. Avoids scattered `htmx:afterSwap` listeners across pages.
  - `onAfterSwap(fn)`  - runs after `#app` is swapped via boosted nav
  - `onAfterSettle(fn)`  - runs after HTMX settle phase (hx-trigger wired up on new elements)
  - `onPageLoad(fn)`  - runs on DOMContentLoaded (full page load only)
  - `onHistoryRestore(fn)`  - runs on browser back/forward (history cache restore)
- Hooks are registered via the barrel export (`import { onAfterSwap } from '@phillipsharring/handlr-frontend'`) or via the app namespace (`App.hooks.onAfterSwap(fn)`).
- `hooks.js` is auto-loaded via `init.js`  - no additional imports needed.

## 0.1.0

Initial release. Nothing changed, except everything.
