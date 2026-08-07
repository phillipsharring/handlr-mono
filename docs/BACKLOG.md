# Handlr backlog

Running list of TODOs that aren't yet ADRs or in-flight work.

## Skeleton drift: framework changes don't reach existing apps

The app skeleton is a **copy-once** starting point (`composer create-project`), not a
dependency. So when a copied file improves — `bootstrap.php`, `config.php`, `routes.php`,
`app.js` — existing apps (reuselists, binder-quest, …) never get it, and we patch each app
by hand. This is the single biggest source of long-term drift across the ecosystem.

Latest instance: 0.16 added a `ChangeRecorder` binding in the Kernel, but the CLI
(`migrate`/`seed`) uses the app's own `handlr_app()` bootstrap, which hand-mirrors the
framework singletons (EventManager, Logger, Db) and hadn't picked up the new one — so
`composer run migrate` threw `Cannot instantiate Handlr\Database\ChangeRecorder`. Patched
per-app (reuselists) and in the skeleton bootstrap; the underlying pattern keeps recurring.

Strategy, cheapest-first:

1. **Shrink the copied surface.** Move framework glue OUT of copied files into the
   versioned package so `composer update` carries it. The bootstrap's framework-singletons
   block is the poster child: either a `Kernel::registerCoreSingletons($container)` that
   both the web Kernel and `handlr_app()` call, **or** a container that falls back to a
   constructor parameter's default when a class-typed dependency can't be resolved (then
   `Table(DbInterface $db, ChangeRecorder $r = new NullRecorder())` autowires with no
   binding at all, in web *and* CLI). The container-default-fallback is the highest-leverage
   single change — it also fixes the `ChangeRecorder` CLI break directly. Rule of thumb: if
   it isn't app-specific, it shouldn't be in the skeleton.
2. **A `doctor` command that teaches the fix.** Grow the skeleton's `scripts/check.php`
   into `composer run doctor` — validate a *generated* app against the current framework
   (required bindings present, framework pins current, no dead module refs, migration
   names derivable) and print exactly what to add. Fail-loud, on-brand.
3. **(heavier, later) Managed regions + an upgrade codemod.** `// handlr:managed` markers
   around framework-owned sections in copied files, plus a `handlr upgrade` that re-stamps
   them per version (Rails `app:update` / Laravel Shift style). Only if 1+2 aren't enough.

Do 1 opportunistically (every "we forgot the skeleton" moment is a candidate to move that
thing into the package); 2 when a release breaks an app; 3 only if drift keeps hurting.

## Container: default-fallback for optional constructor deps

Highest-leverage slice of the "Skeleton drift" item above — pulled out so it doesn't get
lost. When the container autowires a class whose constructor has a class-typed parameter
**with a default** (e.g. `Table(DbInterface $db, ChangeRecorder $r = new NullRecorder())`)
and that type can't be resolved, it currently **throws** instead of using the default. Make
`resolveParameter`/`resolveDependency` fall back to the parameter's default when
`isDefaultValueAvailable()` (required deps with no default still fail loud). Add tests.

Payoff: optional dependencies "just work" in any container (web, CLI, tests) with no
binding, so adding a new core service never again breaks every app's CLI bootstrap. This is
the root cause of the 0.16 `Cannot instantiate Handlr\Database\ChangeRecorder` deploy break
(patched per-app + in the skeleton; this retires those patches). Small, additive.

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

## Skeleton: `config.php` missing `app.url` / `app.name` → email links point to localhost

Found while deploying a fresh-skeleton app (task-queue) to production. The
skeleton's `packages/app/backend/app/config.php` `'app'` block only defines
`env`, `debug`, and `providers`. But `SendVerificationEmailListener` (and any
future email that builds an absolute URL) reads:

```php
$appUrl  = rtrim($this->config->get('app.url', 'http://localhost:5173'), '/');
$appName = $this->config->get('app.name', 'Handlr App');
```

With those keys absent, **every production app silently uses the
`http://localhost:5173` fallback** — verification/reset emails ship a link to
localhost even though `APP_URL` is set in the deployed `.env`. Nothing errors;
the link just goes nowhere.

**Fix (do in the skeleton):** add the two keys to the `'app'` config block so
they read from env, mirroring what the reuselists app already has:

```php
'name' => $_ENV['APP_NAME'] ?? 'Handlr App',
'url'  => $_ENV['APP_URL']  ?? 'http://localhost:5173',
```

That's the whole core fix — one file, two lines.

### Companion UX fixes (same investigation; describe, don't port)

These rode along in the task-queue app. The skeleton has the newer/upgraded
frontend UI, so **re-implement from this description rather than copying the
task-queue files** (the verify-email page markup will differ):

- **`GetAuthStatus` (`/api/auth/me`)** — include `email_verified` on the user
  payload: `'email_verified' => $user?->email_verified_at !== null`. Lets the
  frontend know verification state.
- **`PostResendVerification`** — inject `UsersTable`; if the current user's
  `email_verified_at !== null`, short-circuit with
  `success('Your email is already verified.')` instead of dispatching the
  signup event. As-is it returns "Verification email sent." even though the
  listener no-ops for verified users — a lie to the user.
- **verify-email page** — before/around verifying the token, check
  `/api/auth/me`; if authenticated **and** `email_verified`, show an
  "already verified → Continue" state instead of the "invalid link" error a
  stale/used token produces. Also surface the server's actual resend message
  (so "already verified" shows through) rather than a hardcoded "sent" toast.

Root cause of all four: the skeleton's auth/email scaffolding predates these
niceties; the reuselists app has the config keys but the skeleton never got them.

## ADR 0001 — as-built naming drift (minor)

ADR text sketches `packages/{backend,frontend,build,skeleton,site}`; the repo shipped
`skeleton -> app` and `site -> static`. Worth a one-line "as-built" correction in the
ADR someday. Not urgent.
