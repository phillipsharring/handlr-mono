# ADR 0001 — Combine graspr (FE) and handlr (BE) into one co-versioned framework

- **Status:** Accepted (D1/D2 resolved 2026-06-21)
- **Date:** 2026-06-21
- **Deciders:** Phillip Harrington
- **Supersedes / relates to:** `BACKLOG.md` "Combine graspr + handlr into one framework (the big move)" (items 1–4, 7)

---

## Context

The stack is currently three independently-versioned packages plus a scaffold:

| Package | Registry | Layer | Version (at writing) |
|---|---|---|---|
| `@phillipsharring/graspr-build` | npm | build-time (vite plugin, html compiler, page baker, module-dir resolution, minify) | 0.6.1 |
| `@phillipsharring/graspr-framework` | npm | browser runtime (auth, csrf, htmx wiring, modals, forms, hooks) | 0.2.9 |
| `phillipsharring/handlr-framework` | composer | backend (Handler, Pipe, Table, Query, Db, EventManager, …) | ^0.5 |
| handlr app-skeleton | — | scaffold (frontend + backend halves) | — |

A **module** today ships as **two** packages — an npm package (FE: `pages/`, `components/`, runtime `init`) and a composer package (BE: service provider, migrations). `handlr-module-landing` is the only real one, and it already lives in **one repo with two manifests** (`package.json` + `composer.json`) published in lockstep to npm + Packagist.

Two facts make now the right time:

1. **The ecosystem is normalized.** All five consuming apps (phillipharrington.com, paper-doll, binder-quest, reuselists, streamtostory) now consume the published packages from the registries — **none vendored** — on uniform current versions. The de-vendoring + version sweep that just completed was the on-ramp for this work.
2. **The FE/BE seam is already clean.** The browser-safe `modules.mjs` vs Node-only `module-dirs.mjs` split, and the `devCss` / `meta.redirect` conventions, already define the boundary the merge formalizes.

### The constraint that shapes everything

There is **no such thing as one literal package** here: npm and Packagist are separate registries. "Single framework / single FE+BE module" therefore means **one git repo, two manifests, dual-published, with lockstep versions** — exactly the micro-pattern `handlr-module-landing` already proves. The merge generalizes that pattern up to the framework and skeleton, and makes it *the* convention.

---

## Decision

1. **Combine `graspr-framework` (FE runtime) and `handlr-framework` (BE) into a single co-versioned unit under the "handlr" umbrella**, distributed as one repo with two manifests (npm for the FE runtime, composer for the BE), released in lockstep.
2. **`graspr-build` stays separate** as a build-time peer dependency (per backlog item #7). It is the build tool, not the framework; a handlr app-skel install pulls it in as a peer dep, and a standalone graspr-build install keeps working.
3. **Modules are single FE+BE packages** — one repo, two manifests, lockstep versions, auto-discovered on both sides. This convention is baked into handlr proper.
4. **Prove the convention on `handlr-module-landing` first**, before merging the framework or skeleton.

### Detailed decisions

- **D1 — Repo topology (accepted):** **Monorepo** at `~/Sites/handlr`, `packages/*` layout (the npm/pnpm/Turborepo convention; keeps the root clean for workspace config + `docs/`). Packages:

  ```
  handlr/                         # monorepo root (git repo)
  ├── packages/
  │   ├── backend/                # phillipsharring/handlr (composer) — BE framework
  │   ├── frontend/               # @phillipsharring/handlr (npm) — FE runtime
  │   ├── build/                  # @phillipsharring/handlr-build (npm) — build tooling
  │   ├── skeleton/               # phillipsharring/handlr-app (composer create-project) — full FE+BE app
  │   └── site/                   # @phillipsharring/create-handlr-site (npm) — static-site initializer
  ├── docs/
  ├── package.json                # workspaces: ["packages/*"]
  └── ...
  ```

  - **npm side** uses workspaces natively (`packages/*`).
  - **composer/Packagist side** has no workspaces — `packages/backend` and `packages/skeleton` are **git-subtree-split** to read-only repos that Packagist tracks (the Symfony/Laravel monorepo pattern). This split tooling is the one genuinely new bit of setup.
  - **Modules stay as their own separate repos** (each dual-published), not in this monorepo.

- **D2 — Package identity (accepted):** registry-appropriate spelling of the same logical names:
  - FE runtime — npm `@phillipsharring/handlr`
  - BE framework — composer `phillipsharring/handlr` (composer has no `@scope`; the name is `vendor/package`)
  - Build tooling — npm `@phillipsharring/handlr-build` (renamed from `graspr-build`; "build" fits both static sites and full apps)
  - Full app skeleton — composer `phillipsharring/handlr-app`
  - Static-site initializer — npm `@phillipsharring/create-handlr-site` (npm-only; static sites have no PHP)
  - **The `graspr` name is retired entirely.**
- **D3 — Versioning:** Lockstep single version across the repo's npm + composer artifacts (as landing already syncs both manifests). A release tags once; both registries publish that version.
- **D4 — Module contract:** see below — the canonical single-package module layout.
- **D5 — Skeletons (two of them):**
  - **Full app** (`packages/skeleton` → `phillipsharring/handlr-app`): scaffolded via `composer create-project`; a `post-create-project-cmd` runs `npm install`, pulling `@phillipsharring/handlr` (runtime) + `@phillipsharring/handlr-build` as **devDependencies**. One command, both halves.
  - **Static site** (`packages/site` → `@phillipsharring/create-handlr-site`): scaffolded via `npm create @phillipsharring/handlr-site` / `npx create-handlr-site`; **npm-only**, depends on `@phillipsharring/handlr-build` directly. No composer, no PHP.

### The canonical module contract (D4)

A module is **one repo** containing both halves:

```
handlr-module-<name>/
├── package.json          # npm manifest (FE)   — version X
├── composer.json         # composer manifest (BE) — version X (lockstep)
├── pages/                # FE: route pages (discovered by the build)
├── components/           # FE: components (discovered by the build)
├── src/                  # FE: runtime entry + init()  (browser-safe, no node: imports)
├── app/                  # BE: service provider, handlers, listeners
└── migrations/           # BE: schema the module owns
```

- **FE auto-discovery:** unchanged — the build resolves the module's `pagesDir`/`componentsDir` via `graspr-build/module-dirs` `resolveModuleDirs()`, and the app entry calls `initModules()` (browser-safe, from `graspr-build/modules`).
- **BE auto-discovery:** the module's service provider auto-registers (routes, events, migrations) when the app `composer require`s it — no per-app wiring.
- **Install experience:** one `npm install @phillipsharring/handlr-module-<name>` + one `composer require phillipsharring/handlr-module-<name>`, same version, and both halves wire themselves up.
- **Browser-safety rule (carried from ADR-less precedent):** the runtime `src/` must never import `node:*`; build-time path resolution lives apart. (This is the bug class that broke binder-quest's prod build — codify it, ideally guard it with a test in each module.)

---

## Migration mapping (existing repos → packages)

`~/Sites/handlr` is currently a folder holding ~10 separate git repos. The merge consolidates them:

| Existing repo | → | Monorepo target | Published as |
|---|---|---|---|
| `backend/framework` | → | `packages/backend` | composer `phillipsharring/handlr` |
| `frontend/framework` (graspr-framework) | → | `packages/frontend` | npm `@phillipsharring/handlr` |
| `frontend/build` (graspr-build) | → | `packages/build` | npm `@phillipsharring/handlr-build` |
| `backend/app-skeleton` + `frontend/app-skeleton` | → | `packages/skeleton` | composer `phillipsharring/handlr-app` |
| `frontend/static-skeleton` + `frontend/static-installer` | → | `packages/site` | npm `@phillipsharring/create-handlr-site` |
| `backend/installer`, `frontend/installer` | → | folded into `skeleton`/`site` scaffolding | — |
| `modules/landing` | → | **stays its own repo** (dual-published module) | npm + composer |

History is preserved by importing each repo with `git subtree add --prefix=packages/<name>`. The old per-repo remotes become the subtree-split targets (or are archived in favor of fresh split targets).

**Import churn from the rename (the codemod):** JS only. `@phillipsharring/graspr-framework` → `@phillipsharring/handlr` and `@phillipsharring/graspr-build` → `@phillipsharring/handlr-build`, find-and-replaced across the five apps. The **PHP side has zero churn** — the backend namespace is already `Handlr\…`.

## Consequences

**Positive**
- Modules become a single mental + install unit; no more "did I require both halves at matching versions?"
- FE and BE versions can't drift (lockstep), removing a whole class of skew bugs.
- New apps scaffold from one skeleton against one framework + the build peer dep.
- **App migration is uniform and low-friction** *because of the normalization already done*: every app is on current registry versions, de-vendored, so the migration is "swap deps, update import/namespace, adopt module registration" applied identically across apps.

**Costs / risks**
- A real migration, not a no-op: import paths (JS) and namespaces (PHP) may consolidate/rename; module registration changes.
- Dual-registry release tooling must publish npm + composer in lockstep reliably (landing does it by hand today; the framework will want this scripted).
- `binder-quest` is the **canary** — the only app consuming a module (landing), so it exercises the full FE+BE module path. Migrate it first after the framework lands.
- Short-lived inconsistency while only landing follows the new convention and the framework doesn't yet — acceptable, low blast radius.

---

## Rollout sequence

1. **Spike → this ADR.** D1–D5 resolved. ✅
2. **Stand up the monorepo + migrate `build` as the proof slice** (**the first implementation step**). `git init` at `~/Sites/handlr`, root workspace config, `packages/` scaffold, move `docs/` in (commits this ADR). Import `frontend/build` → `packages/build` via subtree (history preserved), rename to `@phillipsharring/handlr-build`, verify its 40 tests pass from the workspace. **Don't publish or cut over apps yet** — this validates the monorepo/workspace/subtree mechanics on the most self-contained package before anything depends on it.
3. **Migrate `frontend` + `backend`** into `packages/`, and stand up the composer subtree-split publishing for `packages/backend`.
4. **Cut over the rename:** publish `@phillipsharring/handlr`, `@phillipsharring/handlr-build`, `phillipsharring/handlr`; run the JS codemod across the five apps (`graspr-*` → `handlr-*`); update deps.
5. **Standardize `landing`** to the canonical module contract (backlog #3); verify end-to-end via binder-quest (the canary).
6. **Build the skeletons** — `packages/skeleton` (full app, `composer create-project` + post-install `npm i`) and `packages/site` (`create-handlr-site`) (backlog #1).
7. **Extract A/B testing into a module** (backlog #2) as the second proof of the module convention.
8. **Migrate apps** onto the combined framework + module convention — **binder-quest first**, then the rest; uniform delta thanks to normalization.

---

## Open questions

- **Release tooling:** how lockstep npm+composer publishing + the composer subtree-split are automated (Make targets? CI? `symplify/monorepo-builder`?). To settle before step 4 (cut-over), not before step 2.
- **History preservation mechanics:** `git subtree add` per package vs `git filter-repo` import; and the fate of the old per-repo GitHub remotes (become split targets vs archive). Settle as part of step 2.
- **Skeleton install ergonomics:** confirm the full-app `post-create-project-cmd` → `npm install` flow, and that `handlr-build` lands correctly as a devDependency in both skeletons (backlog #7).

*Resolved:* D1 (monorepo, `packages/{backend,frontend,build,skeleton,site}`), D2 (names; `graspr` retired), and the rename approach (one-time JS codemod, no shim; PHP namespace unchanged).
