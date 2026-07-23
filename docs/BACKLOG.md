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
