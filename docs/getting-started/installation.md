# Installation

> [!NOTE] Draft
> This page is a stub. Content coming.

## Scaffold a full app

```bash
composer create-project phillipsharring/handlr-app my-project
```

A `post-create-project-cmd` runs `npm install` in the frontend half, so one command sets up both sides.

## Add to an existing project

Backend only:

```bash
composer require phillipsharring/handlr-backend
```

Frontend only:

```bash
npm install @phillipsharring/handlr-frontend
npm install htmx.org handlebars sortablejs   # peer deps
```

## To cover

- Requirements (PHP 8.4+, Node version)
- Directory layout after scaffold
- Environment / `.env` setup
- First migration + serve
