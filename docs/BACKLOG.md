# Handlr backlog

Running list of TODOs that aren't yet ADRs or in-flight work.

## `module:install` composer script (referenced by handlr-app README)

`packages/app/README.md` documents `composer run module:install -- <name>`, but no
such script exists yet. The need: a module is dual-published (composer + npm), and
a plain `composer require` can't also run `npm install` because composer doesn't run
from the npm root. So we want a single command that installs **both halves** of a
module at the matching version.

Plan:
- Add a `module:install` script to **handlr-backend** (`packages/backend`), alongside
  the existing `migrate` / `make:*` scripts, that does the composer require **and** the
  npm install of `@phillipsharring/handlr-module-<name>` (in the frontend half).
- **handlr-app** references it from `backend/composer.json` as a `scripts` entry, the
  same way it references `migrate`, `seed`, etc. (delegating to the framework script).
- Keep the README line as-is until then (it documents the intended UX).

Status: DONE (2026-06-29). Implemented as `packages/backend/scripts/module.php`
(Symfony Console single-command, mirrors `migrate.php`). Installs the composer half,
reads the resolved version back from `composer.lock`, then npm-installs the frontend
half pinned to that exact version (so an app on an older module version still gets a
matching frontend). Does NOT register the provider or run migrations  - prints those as
next steps by design. Wired in `packages/app/backend/composer.json` as `module:install`
(usage: `composer run module:install -- <name>`). Ships in the next handlr release;
the published 0.8.0 backend predates the script, so the first real install (re-adding a
module to an app) is the verification canary.

## ADR 0001 — as-built naming drift (minor)

ADR text sketches `packages/{backend,frontend,build,skeleton,site}`; the repo shipped
`skeleton -> app` and `site -> static`. Worth a one-line "as-built" correction in the
ADR someday. Not urgent.
