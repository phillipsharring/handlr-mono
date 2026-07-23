# ADR 0002 — Declarative HTMX behavior layer (kill the `addEventListener` boilerplate)

- **Status:** Proposed
- **Date:** 2026-07-22
- **Deciders:** Phillip Harrington
- **Relates to:** `0001-combine-graspr-and-handlr.md`; `packages/frontend/CLAUDE.md` ("a toolkit on top of HTMX, not a framework" — GUIDING)

---

## Context

handlr-frontend is a toolkit layered on HTMX. Yet every consuming app hand-rolls
large amounts of raw `document.addEventListener(...)` glue for the *same* handful
of jobs. A sweep of the five apps that consume the framework today:

| Bucket | Raw listeners (5 apps) | What the code does | Framework covers? |
|---|---|---|---|
| **Click delegation** | ~229 `click` | `document.on('click', e => e.target.closest('[data-x]'))` → do a thing | ❌ nothing |
| **Form response branching** | ~34 `htmx:afterRequest` | on success: `meta.redirect` → navigate, or hide form + reveal a success node, or toast | ⚠️ `core/forms.js` only handles error rendering |
| **Lifecycle re-init** | ~34 `afterSwap` + `afterSettle` | re-run pagination / table-sort / widget init on swapped content | ✅ `hooks.js` (`onAfterSwap` etc.), underused |
| **Request decoration** | ~11 `htmx:configRequest` | add headers / collect array params | ✅ `core/csrf.js` only |

Two buckets are effectively uncovered — **click delegation** and **form-response
branching** — and together they are ~80% of the noise. They are also *near
duplicate* across apps: every app's `login` / `signup` / `forgot-password` /
`reset-password` pages carry the same "afterRequest → parse JSON → redirect-or-
reveal-success" script, and the `logout-link` handler is byte-identical across
multiple files within a single app.

### Why the boilerplate exists (and what our job actually is)

HTMX is already declarative for the *request* (`hx-*`). The residual JS exists for
the gaps HTMX does **not** cover:

1. **Post-response branching** — same 2xx, different UI outcome (navigate vs reveal
   vs toast), decided from the response body.
2. **Click actions** not worth a server round-trip — toggles, copy-to-clipboard,
   open-a-modal.
3. **Request decoration** — headers / params (already handled for CSRF).

So handlr's job is **not to wrap HTMX** — it is to **fill HTMX's declarative gaps**
with (a) a few more `data-*` attributes and (b) a thin imperative registry for the
cases an attribute can't express. That framing keeps us HTMX-idiomatic instead of
building a competing control-flow framework.

### The precedent already exists

`core/csrf.js` (one body `htmx:configRequest` listener) and `core/forms.js` (one
body `htmx:afterRequest` listener) already prove the shape: **one delegated
body-level listener owned by the framework, behavior driven by attributes/markup.**
This ADR generalizes that shape to clicks and to form-response branching. It is
consistent with existing architecture, not a new paradigm. It also sidesteps the
listener-accumulation pitfall (frontend CLAUDE.md pitfall #6): one body listener
survives boosted nav; per-element listeners inside `#app` are GC'd and re-added on
every swap.

### The guiding constraint

Per `packages/frontend/CLAUDE.md`: **apps call us; we do not call apps.** `index.js`
is a pure, side-effect-free barrel; `./init` is the opt-in batteries bundle. Any new
capability must be **a named `initX()` export that `./init` calls** — never a module
that self-registers listeners at import time. This ADR follows that ideal for all new
code (and deliberately does *not* add to the known "self-registering `core/*`" debt).

---

## Decision

Add a **declarative HTMX behavior layer** to `packages/frontend`, in four passes.
`packages/backend` needs no changes. `packages/static` (the static-site skeleton,
no HTML runtime) is explicitly **out of scope** and gets none of this.

### D1 — Two coordinated surfaces: declarative attributes (A) + imperative sugar (B)

Ship **A and B together in pass 1** — B is the escape hatch that guarantees we never
lose power versus raw `addEventListener`.

**A — Declarative `data-*` attributes** (the 80% case, HTMX-idiomatic):

*Clicks* — a named-action registry read by one delegated listener:

```html
<button data-action="archive-task" data-id="…">Archive</button>
<button data-toggle="#filters">Filters</button>   <!-- built-in action -->
```

- `data-action="name"` → looks up a registered handler, calls `handler(el, event)`.
- A small set of **built-in actions** ships registered: at minimum `toggle`
  (`data-toggle="#sel"` show/hide), `copy` (copy `data-copy` / element text). Keep the
  built-in set deliberately tiny; apps register their own domain actions.

*Form-response branching* — `data-on-success` / `data-on-error` on the requesting
element, read by one delegated `htmx:afterRequest` listener:

```html
<form hx-post="/api/auth/login" data-on-success="redirect">…</form>
<form hx-post="/api/auth/forgot" data-on-success="reveal:#forgot-success">…</form>
<form hx-post="/api/things"      data-on-success="toast:Saved">…</form>
```

Verbs (v1, single-verb per attribute):
- `redirect` → navigate to `meta.redirect` in the parsed JSON body (fallback:
  `data-redirect-url`).
- `reveal:#sel` → hide the requesting form, `hidden`-reveal the target node.
- `toast:Message` → fire a toast (reuses `HandlrToast`).
- `action:name` → run a registered action — the bridge from A into B.

Unrecognized/absent attributes = no-op (fully backward compatible; existing inline
scripts keep working untouched).

**B — Imperative named exports** (the custom 20%), all thin wrappers over the *same*
central delegation so each event still has exactly one body listener:

```js
import { registerAction, onClick, onFormSuccess, onFormError, onHtmx }
  from '@phillipsharring/handlr-frontend';

registerAction('archive-task', (el) => { … });     // powers data-action="archive-task"
onClick('[data-star]', (el, e) => { … });           // delegated click, no closest() boilerplate
onFormSuccess('#signup form', (data, form) => { … });// parsed JSON + form element
onHtmx('htmx:afterSettle', '#grid', (el, e) => { … });// generic delegated htmx event
```

`registerAction` and `onAction` are the same primitive (alias). `onFormSuccess`
pre-filters `e.detail.successful` and hands back parsed JSON so callers stop
re-writing the try/parse dance.

### D2 — Registry lives in the framework, not on `window.App` (no namespace soup)

The action registry is a **module-scoped `Map` inside `packages/frontend`**, mutated
only through the exported `registerAction` / `onAction`. It does **not** hang off
`window.App.actions`. Apps that want app-side access re-export deliberately in their
own `namespace.js`; the framework never claims global namespace real estate. Keeps
the registry testable and avoids the global-object soup.

### D3 — New code is `initX()`-shaped, wired through `./init` (honors the toolkit ideal)

New modules expose explicit init functions and are called by the `./init` bundle —
they do **not** self-register at import:

```
packages/frontend/src/core/delegation.js    // shared: one body listener per (event,type); onClick/onHtmx primitives
packages/frontend/src/core/actions.js        // registerAction/onAction + built-in actions + initActions()
packages/frontend/src/core/form-response.js  // data-on-success/-error + onFormSuccess/onFormError + initFormResponses()
```

- `index.js` (pure barrel) gains the named exports: `registerAction`, `onAction`,
  `onClick`, `onHtmx`, `onFormSuccess`, `onFormError`, `initActions`,
  `initFormResponses`. Zero side effects on import — importing the barrel still runs
  nothing.
- `init.js` (batteries) calls `initActions()` and `initFormResponses()` alongside the
  existing csrf/forms/etc. This is the single line that "turns the layer on."
- À la carte remains possible: an app importing only the barrel can call
  `initActions()` itself without pulling the whole `./init` bundle.

This sets the *good* precedent the frontend CLAUDE.md calls the "toolkit-ideal
refactor," rather than adding to the existing self-registering-`core/*` debt.

### D4 — Rollout in ordered passes

- **Pass 1 — A + B core (this ADR's build).** Implement `delegation.js`,
  `actions.js`, `form-response.js`; add exports; wire `./init`; unit tests; document
  in `packages/frontend/CLAUDE.md`. Purely additive — nothing existing breaks.
- **Pass 2 — C, the auth-flow preset.** Express the recurring auth flows
  (login/signup redirect, forgot/reset reveal-success, logout→home) using the pass-1
  primitives. Expected new framework code is small: the `redirect` / `reveal` verbs
  already cover the forms; C mainly adds a built-in `logout` action (and, if useful,
  an opt-in `initAuthFlows()` that registers it). Mostly a *preset + documented
  attribute recipe*, not a new subsystem.
- **Pass 3 — D, sweep the app skeleton (`packages/app`).** Replace the hand-rolled
  `addEventListener` scripts in the skeleton's auth pages, layouts, and components
  with the attributes / named exports. This is the skeleton only — the shipped
  starter every new app is cloned from.
- **Pass 4 — first-party modules (`~/Sites/handlr/modules/{ab,landing}`).** Both
  first-party modules already consume the pattern this ADR replaces and must be
  updated in lockstep (they are published, versioned artifacts):
  - **landing** — `src/index.js` hand-rolls a `document.addEventListener('submit', …)`
    email-capture handler → convert to a delegated `onFormSuccess` (or `data-on-success`
    on the form markup in `pages/`).
  - **ab** — admin page inline scripts use `App.ui.openFormModal` / `App.getRouteParams`
    / `App.escapeHtml` and a `btn.addEventListener('click', …)`; `src/runtime.js` owns a
    `document.addEventListener('click', …)` delegation for `data-ab-capture`. The click
    delegation can move to `registerAction` / `onClick`; the modal-open button becomes
    `data-action`.

    Each updated module gets a lockstep minor bump (per the module version rule) and a
    republish — flagged, not auto-done.
- **Out of scope (noted, not done here):** the five *existing* consuming apps
  (reuselists, binder-quest, streamtostory, paper-doll, task-queue) migrate on their
  own cadence once the layer ships; backward compatibility means there is no forced
  flag day. **`packages/static` gets nothing** — it has no HTML runtime.

### D5 — `data-*` namespace, lockstep version

- Attributes stay in the **`data-*` namespace** (portable, composes with `hx-*`; no
  `hx-on`-style custom prefix).
- Pass 1 adds named exports ⇒ **minor** version bump of `@phillipsharring/handlr-frontend`,
  co-versioned lockstep with `phillipsharring/handlr` per ADR 0001 (backend unchanged,
  but manifests bump together by convention).

---

## Consequences

**Positive**
- Kills ~80% of per-app front-end glue (clicks + form-response), and the most
  duplicated chunk (auth pages) becomes attribute markup.
- One body listener per event instead of N per-element listeners → fewer boosted-nav
  leaks (pitfall #6), less to get wrong.
- Consistent with existing csrf/forms delegation; no new mental model.
- Sets the toolkit-ideal `initX()` precedent instead of growing the self-registering
  debt.

**Negative / risks**
- Two ways to express a click (`data-action` attribute vs `onClick` export) — must
  document *when* to reach for each (attribute for markup-expressible; export for
  logic-heavy). Mitigated by the `action:name` bridge keeping one underlying registry.
- Attribute verbs are a mini-DSL; scope creep risk. Mitigation: v1 is the four verbs
  above, single-verb-per-attribute; anything richer goes through `action:`.
- Migrating the existing five apps is real work; deliberately deferred and optional
  thanks to backward compatibility.

**Neutral**
- Backend untouched. Static skeleton untouched.

---

## Open questions (resolve during pass 1)

1. Should `data-on-success` accept **multiple** verbs (e.g. `toast:Saved; reveal:#x`)
   or stay single-verb with `action:` as the composition path? (Lean: single-verb v1.)
2. Do we ship `copy` / `open-modal` as **built-in actions**, or only `toggle` and let
   apps register the rest? (Lean: `toggle` + `copy` built-in; modal opening already
   has `App.ui.openFormModal`.)
3. Does `onFormSuccess` parse JSON eagerly (convenient) or hand back the raw XHR
   (flexible)? (Lean: parse, with the raw XHR also on the callback's second/third arg.)
