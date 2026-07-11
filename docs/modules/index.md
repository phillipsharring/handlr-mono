# Writing a Module

> [!NOTE] Draft
> This page is a stub. Content coming.

Modules are self-contained feature packages. A module can provide frontend pages/components, a backend service provider, or both — one repo, two manifests (`package.json` + `composer.json`).

## To cover

- Module structure (`src/index.js`, `pages/`, `components/`, `app/`, `migrations/`)
- Build-time vs runtime JS (guard Node APIs behind `typeof process`)
- Connecting a module: `site.config.js`, `initModules()`, backend `providers`
- Configuration & overrides with `configure()`
- Distribution matrix (npm / Packagist / both)
- Local development with volume mounts + symlinks
