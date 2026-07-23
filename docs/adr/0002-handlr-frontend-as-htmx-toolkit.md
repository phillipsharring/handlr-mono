# ADR 0002 — handlr-frontend as a deliberate HTMX toolkit (post-merge direction)

- **Status:** Accepted 2026-07-08
- **Deciders:** Phillip Harrington
- **Relates to:** ADR 0001 (combine graspr + handlr); BACKLOG.md "graspr as a
  deliberate toolkit (not a framework)" and the straight-HTMX item.

---

## Context

ADR 0001's merge is fully rolled out: the monorepo ships co-versioned packages,
and all consuming apps (binder-quest, paper-doll, reuselists, streamtostory,
phillipharrington.com) are on the same lockstep framework version (0.10.2) with
no vendored copies and no version drift. That hurdle is behind us.

Two backlog items remained about *positioning* rather than mechanics:

1. **"Codify graspr as a deliberate toolkit, not a framework."** The FE layer
   (now `@phillipsharring/handlr-frontend`, the retired `graspr-framework`) grew
   into a toolkit shape organically rather than by design. Its structure is
   already toolkit-first — but its naming and some internals still read
   "framework."
2. **"Make straight HTMX a first-class option."** The backend already renders
   parsed server-side templates (`ViewPipe` + `Views/View.php`), but the default
   app path is API-JSON + client-side templates. Straight-HTMX (server-rendered
   fragments swapped by HTMX) is not yet a blessed, scaffolded option.

## Decision

1. **handlr-frontend is a composable toolkit on top of HTMX, not an
   inversion-of-control framework.** Apps call it; it does not call apps. This is
   a guiding principle for everything added or changed in `packages/frontend`.

2. **The two-entry contract is the toolkit's shape and must be preserved:**
   - `.` (`src/index.js`) — a **pure barrel of named exports with zero side
     effects**. Importing it runs nothing; apps take only the pieces they call
     (tree-shakeable). Never import a self-registering module from the barrel.
   - `./init` (`src/init.js`) — the **opt-in batteries** bundle that wires the
     side-effecting listeners (csrf, boosted-nav, auth-state, forms, search,
     sortable). Framework *feel*, always opt-in, never the API surface.
   - **New capability ⇒ a named export / explicit `initX()`** (like
     `initPagination`, `initTableSort`, `initClickBurst`), not a module that
     self-registers listeners at import time. Everything `./init` turns on must
     also be usable à la carte without it.

3. **Straight-HTMX is a supported opt-in path; API/JSON stays the default.** The
   backend half exists (`ViewPipe`). Offering it end-to-end needs only FE-side
   conventions + one worked skeleton example + a Tailwind `@source` pointed at the
   backend views:
   ```css
   /* app frontend/src/styles/style.css */
   @source "../../../backend/resources/views/**/*.{php,html}";
   ```
   This works because `frontend/` and `backend/` are siblings in each app repo
   (no mono dependency). **Accepted trade-off:** Tailwind scanning happens at FE
   build time, so classes added to server-rendered views only reach the shipped
   CSS after a FE rebuild. Fine until it becomes annoying; revisit then.

## Known debt (deferred, not blocking)

- The `./init` modules (`csrf`, `boosted-nav`, `auth-state`, `forms`, `search`,
  `sortable`) still **self-register on import** rather than exposing `initX()`
  functions, so they can't yet be taken individually. The toolkit-ideal refactor
  is to give each an init function and have `./init` call them all. Do it when
  real à-la-carte demand appears.
- Package headers/comments still say "Framework." The *structure* is toolkit-first;
  the naming lags the design. Rename opportunistically.

## Consequences

- The FE positioning is now explicit and recorded, so future additions default to
  the toolkit shape instead of drifting back toward framework coupling.
- `index.js` staying side-effect-free keeps `import { x }` from secretly booting
  the runtime — the property that makes the toolkit claim true.
- Straight-HTMX becomes a documented choice apps can reach for, without disturbing
  the API/JSON default.

## Notes

- The full operational version of this principle also lives in
  `packages/frontend/CLAUDE.md`, but that file is **gitignored (local agent
  notes)** — so **this ADR is the canonical, version-controlled record.**
