# Writing a Module

A **module** is a self-contained feature package. It can ship frontend
pages/components, a backend [service provider](/backend/service-providers), or both —
one repo with two manifests (`package.json` + `composer.json`). The shipped modules,
[Landing](./landing) and [A/B Testing](./ab), are the reference examples.

The design goal: installing a feature is adding a dependency, not copying code. A
module brings its own routes, migrations, pages, and components, and slots into the
host app through the same seams your own code uses — [junctions](/backend/routing#junctions)
on the backend, extra content dirs on the frontend.

## Structure

```
handlr-module-widgets/
├── composer.json           # phillipsharring/handlr-module-widgets (backend half)
├── package.json            # @phillipsharring/handlr-module-widgets (frontend half)
├── app/                    # backend: WidgetsServiceProvider, handlers, table, record
├── migrations/             # backend: schema the module owns
├── pages/                  # frontend: HTML pages, routed like the app's own
├── components/             # frontend: custom-tag templates
└── src/index.js            # frontend: the module object (init, config, dir resolution)
```

## Two halves, matching versions

A dual-published module is one version number across two registries. The
[`module:install`](/backend/cli#installing-a-module-module-install) command installs
both halves at the matching version:

```bash
php vendor/bin/handlr module:install widgets
```

It installs the composer package and its npm counterpart, but does **not** auto-register
the provider — you wire it up (below), which keeps installation explicit.

## Build-time vs runtime JS

The frontend half has two kinds of code, and mixing them breaks the browser build:

- **Runtime** (`src/index.js`, browser) — the module object. Uses only browser-safe
  helpers from `@phillipsharring/handlr-build/modules`: `moduleRoot(import.meta.url)`
  (to self-resolve its own `pagesDir`/`componentsDir`), `configure`, `initModules`.
  **No `node:` imports.**
- **Build-time** (config/build scripts) — resolving module dirs uses
  `@phillipsharring/handlr-build/module-dirs` (`resolveModuleDirs`), which touches the
  filesystem. That subpath is build-only; never import it into a browser bundle.

```js
// src/index.js — the module object (browser-safe)
import { moduleRoot } from '@phillipsharring/handlr-build/modules';

const root = moduleRoot(import.meta.url);
export default {
    name: 'widgets',
    pagesDir: `${root}/pages`,
    componentsDir: `${root}/components`,
    defaults: { adminNav: '/admin/widgets/' },
    init() { /* runtime wiring, runs via initModules() */ },
};
```

## Connecting a module

**Frontend** — add the module object to `site.config.js`, and init it in your app entry:

```js
// site.config.js
import widgets from '@phillipsharring/handlr-module-widgets';
export default { /* … */ modules: [widgets] };
```

```js
// app entry
import { initModules } from '@phillipsharring/handlr-build/modules';
import widgets from '@phillipsharring/handlr-module-widgets';
initModules([widgets]);
```

The build's `resolveModuleDirs()` appends the module's `pages/` and `components/` after
the app's own, so a module page routes exactly like an app page (route collisions across
the app and its modules are a hard error). A module that declares `adminNav` contributes
a link to the layout's `[[moduleAdminNav]]` slot.

**Backend** — add the module's provider to your app's provider list (in `config/`).
From there it's an ordinary provider: its `routes()` fill your junctions, its `events()`
register, its `migrationPaths()` are picked up by `migrate`.

## Configuration & overrides

`configure(mod, overrides)` returns a copy of the module with `config` merged over its
`defaults` — so a host app tweaks a module without forking it:

```js
import { configure } from '@phillipsharring/handlr-build/modules';
import widgets from '@phillipsharring/handlr-module-widgets';

export default { modules: [configure(widgets, { adminNav: false })] };
```

## Distribution matrix

| Module provides | Publish to |
|---|---|
| Frontend pages/components only | npm |
| Backend provider only | Packagist |
| Both | npm **and** Packagist, same version |

Co-version the two halves so `module:install <name>` resolves a consistent pair.

## Local development

Develop a module against a host app with a volume mount (or a Composer path repo / npm
`link`) so edits are live without re-publishing. Keep the two halves version-locked in
lockstep as you go, the same way the framework packages are.

## See also

- [Service Providers](/backend/service-providers) — the backend half is one.
- [Build](/frontend/build) — how module pages/components are discovered.
- [Landing](./landing) · [A/B Testing](./ab) — worked module examples.
