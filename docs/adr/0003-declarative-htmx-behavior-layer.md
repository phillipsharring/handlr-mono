# ADR 0003 — Declarative HTMX behavior layer (kill the `addEventListener` boilerplate)

- **Status:** Accepted 2026-07-22
- **Date:** 2026-07-22
- **Deciders:** Phillip Harrington
- **Relates to:** `0002-handlr-frontend-as-htmx-toolkit.md` (the toolkit principle this
  ADR operationalizes — canonical since `packages/frontend/CLAUDE.md` is gitignored);
  `0001-combine-graspr-and-handlr.md`

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
- **Built-in actions ship registered (resolved Q2): `toggle` and `copy`.**
  - `toggle` — `data-toggle="#sel"` is sugar for `data-action="toggle" data-target="#sel"`
    (show/hide the target).
  - `copy` — copy `data-copy` (or the element's text) to the clipboard. Supersedes the
    existing bespoke `initCopyIdHandler()`.
  - **No built-in `open-modal`.** Modal opening is already covered richly by
    `App.ui.openFormModal` (templateId / formUrl / fields / size) and the underlying
    `openGlobalModal` — which *already* supports the three variants **modal, confirm
    (`HandlrConfirm`), and takeover (`options.takeover` → `.modal-takeover`)** framework-side
    (takeover is not binder-quest-only). Those stay the imperative surface; an app that
    wants a declarative open registers its own `data-action`. A first-class
    `data-action="open-modal"` can be added later as a built-in if demand appears — out
    of scope for v1.
  - Keep the built-in set deliberately tiny; apps register their own domain actions.

**Direction on `window.App`.** Built-in actions live in the framework registry (D2), *not*
on `window.App`. `window.App` remains an app-owned convenience namespace for imperative
calls from inline scripts (`App.ui.openFormModal`, `App.escapeHtml`, …); it is **not**
deprecated, but it is **not** the registration/dispatch channel for this layer. Net
direction: markup dispatches via `data-*`, runtime JS binds via named exports, and
`window.App` shrinks toward "handy imperative shortcuts" rather than "the API."

*Form-response branching* — `data-on-success` / `data-on-error` on the requesting
element, read by one delegated `htmx:afterRequest` listener:

```html
<form hx-post="/api/auth/login" data-on-success="redirect">…</form>
<form hx-post="/api/auth/forgot" data-on-success="reveal:#forgot-success">…</form>
<form hx-post="/api/things"      data-on-success="toast:Saved">…</form>
```

Verbs (v1 — **single verb per attribute**, resolved Q1):
- `redirect` → navigate to `meta.redirect` in the parsed JSON body (fallback:
  `data-redirect-url`).
- `reveal:#sel` → **compound-but-atomic**: hide the requesting form *and* `hidden`-reveal
  the target node. This is one verb, not a chain — it matches the exact two-op pattern
  every app hand-rolls today (`form.classList.add('hidden')` +
  `success.classList.remove('hidden')`).
- `toast:Message` → fire a toast (reuses `HandlrToast`).
- `action:name` → run a registered action — the bridge from A into B.

**Single-verb, confirmed by the ecosystem sweep:** across all five apps, every success
path is exactly *one* outcome — `redirect`, or the `reveal` pair. No app chains
independent outcomes (no toast-then-redirect, etc.). So multi-verb parsing buys nothing
now; anything richer later goes through `action:name` (compose in JS), which keeps the
attribute grammar trivially explicit. If a genuine chain need appears, revisit — adding
multi-verb later is backward compatible.

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
pre-filters `e.detail.successful` and (resolved Q3) **parses the JSON body eagerly**,
so callers stop re-writing the try/parse dance — the signature is
`onFormSuccess(sel, (data, form, xhr) => …)`: parsed `data` first for the common case,
with the raw `form` and `xhr` also passed for the cases that need headers/status/text or
a non-JSON body. A parse failure yields `data === null` (callback still fires with the
raw `xhr`), never a thrown error.

### D2 — Registry lives in the framework, not on `window.App` (no namespace soup)

The action registry is a **module-scoped `Map` inside `packages/frontend`**, mutated
only through the exported `registerAction` / `onAction`. It does **not** hang off
`window.App.actions`. Apps that want app-side access re-export deliberately in their
own `namespace.js`; the framework never claims global namespace real estate. Keeps
the registry testable and avoids the global-object soup.

**Interaction with the existing `window.App` coupling.** Today, inline page scripts —
including those in the first-party modules (`ab` uses `App.ui.openFormModal` /
`App.getRouteParams` / `App.escapeHtml`; see Pass 4) — reach the framework through the
app-defined `window.App` namespace, because an inline `<script>` can't `import`. The
new layer *reduces* that coupling rather than extending it: module/page **markup** uses
`data-action` / `data-on-success` with no namespace dependency at all, and module
**runtime JS** (`src/*.js`, which can `import`) registers behavior via the named exports
directly. `window.App` stays an app-owned convenience, never a registration channel.

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

### D6 — Naming symmetry: the attribute and the function share the token (defuses "two ways")

The "two ways to express a click" concern is mostly a *framing* problem — resolve it by
making the declarative and imperative surfaces **name-symmetric around a shared token**,
and by positioning them as **one primary path + one escape hatch**, not two peers:

| Concept | Declarative (markup) | Imperative (JS) | Shared token |
|---|---|---|---|
| Named click behavior | `data-action="archive"` | `registerAction('archive', fn)` / `onAction` | `action` / the name `archive` |
| Form success | `data-on-success="redirect"` | `onFormSuccess(sel, fn)` | `success` |
| Form error | `data-on-error="…"` | `onFormError(sel, fn)` | `error` |
| Built-in toggle | `data-toggle="#x"` | (built-in `action` `toggle`) | `toggle` |

Two rules make this coherent:

1. **`data-action` + `registerAction` are not two ways — they are the two *halves* of one
   way.** The attribute is the *invocation site*; the export is the *definition*. They are
   already unified by the action name — that is the entire point of the registry. There is
   no duplicated behavior, only "define once, invoke from markup."

2. **`onClick(selector, fn)` is the escape hatch, documented as secondary.** It is the
   only genuinely-separate mechanism (arbitrary selector, no registration, no name). Use it
   when a behavior isn't worth naming or must match a selector rather than a `data-action`.
   Docs will state the rule plainly: *reach for `data-action` + `registerAction` by default;
   drop to `onClick` only when you don't want a named action.*

Same principle answers "toggle vs openModal": `toggle` is a **built-in action name**
(symmetric: `data-toggle` ⇔ registered `toggle` action), whereas modal-opening stays the
richer imperative `App.ui.openFormModal` and is deliberately *not* mirrored as a v1
attribute (see D1). We do not force symmetry where the imperative API is genuinely richer
than any attribute could be.

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

## Resolved questions

1. **Multi-verb `data-on-success`?** → **No — single verb per attribute.** The ecosystem
   sweep shows every success path is one outcome (`redirect` or the `reveal` pair); no app
   chains outcomes. `action:name` is the composition escape hatch. Multi-verb can be added
   later backward-compatibly if a real chain need appears. (See D1.)
2. **Which built-in actions?** → **`toggle` + `copy`.** No built-in `open-modal`
   (`App.ui.openFormModal` covers it richly; modal/confirm/takeover already exist
   framework-side). Built-ins live in the framework registry, not on `window.App`. (See D1.)
3. **`onFormSuccess` payload?** → **Parse JSON eagerly**, signature
   `(data, form, xhr) => …`; parse failure ⇒ `data === null`, never throws. (See D1/B.)
4. **"Two ways to express a click"?** → Defused via **name symmetry + primary/escape-hatch
   positioning** (D6): `data-action` and `registerAction` are two halves of one path;
   `onClick` is the documented secondary escape hatch.

## Docs consolidation (resolved 2026-07-22)

- **`~/Sites/handlr/mono/docs` is the single canonical, published (VitePress) docs home and
  the source of truth for all ADRs.** ADR 0002 (`handlr-frontend-as-htmx-toolkit`) was
  ported into the mono tree; the duplicate ADR copies under the umbrella
  `~/Sites/handlr/docs/adr` were removed (the umbrella `docs/` keeps only its
  umbrella-specific `MAKEFILE.md`).
- The two divergent `0001` copies were **reconciled into the mono copy** — richer as-built
  facts (package suffixes, app-migration status) folded in, and the incorrect
  `~/Sites/handlr-mono` path corrected to `~/Sites/handlr/mono`.
- The umbrella `~/Sites/handlr` repo remains, but only for umbrella concerns (`Makefile`,
  `BACKLOG.md`, `modules/`, and the nested `mono/` repo) — **not** docs/ADRs.

## Follow-up (own ADR)

- **Modal API unification (future ADR 0004).** modal / confirm / takeover are referenced
  here only to justify *not* shipping a built-in `open-modal` action in v1. Unifying/cleaning
  the modal API is deferred to its own ADR. During passes 1–4 we still fix modal-adjacent
  issues opportunistically, but the cleanup itself is out of scope here.
