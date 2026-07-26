# Installation

## Requirements

- **PHP 8.4+** with PDO MySQL.
- **MySQL 8** (or compatible).
- **Node 18+** for the frontend build.
- **Composer** and **npm**.

## Scaffold a full app

The fastest path is the app skeleton — it sets up both halves (a PHP backend and a
Vite frontend) in one repo:

```bash
composer create-project phillipsharring/handlr-app my-project
```

A `post-create-project-cmd` runs `npm install` in the frontend half, so one command
sets up both sides. The result is two sibling directories:

```
my-project/
├── backend/      # the Handlr PHP app — app/, bootstrap.php, config, migrations, public/
└── frontend/     # the Vite app — content/, src/, site.config.js, vite.config.js
```

The two are **siblings, not coupled by a package** — the frontend talks to the backend
over HTTP, which is what lets a Tailwind `@source` in the frontend scan the backend's
views for a straight-HTMX app.

## Add to an existing project

Backend only:

```bash
composer require phillipsharring/handlr-backend
```

Frontend runtime only:

```bash
npm install @phillipsharring/handlr-frontend
npm install htmx.org handlebars sortablejs   # peer deps
```

Build tooling only (for a static site — no runtime):

```bash
npm install -D @phillipsharring/handlr-build
```

## Environment

Backend config comes from a `.env` file next to the config directory (loaded via
`vlucas/phpdotenv`) plus a PHP config file. At minimum, set the database DSN:

```bash
# backend/.env
DB_DSN="mysql:host=127.0.0.1;port=3306;dbname=my_project"
DB_USER="root"
DB_PASSWORD=""
```

> [!NOTE] Env precedence
> `Dotenv::safeLoad()` does **not** override variables already present in the
> environment. So real environment variables (e.g. a Docker Compose `environment:`
> block) win over `.env`. Keep that in mind when a value seems to ignore your `.env`.

## First run

Everything is driven by composer/npm scripts — there is no `handlr` binary.

```bash
# backend — create the schema, then serve the API
cd backend
composer run migrate       # run the next migration batch (composer run fresh = migrate:fresh + seed:fresh)
composer run dev           # PHP server on http://localhost:8000

# frontend — dev server with live-baked pages (in a second terminal)
cd frontend
npm run dev                # Vite on http://localhost:5173, proxies /api to :8000
```

The frontend dev server ([handlr-build](/frontend/build)'s Vite plugin) renders pages
on the fly and proxies `/api` to the backend, so the two halves run side by side. See
[CLI & Makers](/backend/cli) for the full command list.

## Next

- [Your First App](./first-app) — build an endpoint and a page end to end.
- [Backend Overview](/backend/) · [Frontend Overview](/frontend/).
