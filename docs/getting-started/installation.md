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

```bash
# backend — create the schema and serve
cd backend
php vendor/bin/handlr migrate up        # run migrations
php -S localhost:8000 -t public         # or your preferred server

# frontend — dev server with live-baked pages
cd ../frontend
npm run dev
```

The frontend dev server ([handlr-build](/frontend/build)'s Vite plugin) renders pages
on the fly; the backend serves the API. Point the frontend at the backend's URL in
your dev config.

## Next

- [Your First App](./first-app) — build an endpoint and a page end to end.
- [Backend Overview](/backend/) · [Frontend Overview](/frontend/).
