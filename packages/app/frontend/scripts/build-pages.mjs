#!/usr/bin/env node
/**
 * Production page baker for the app.
 *
 * Replaces the `handlr-build-pages` bin shim with a thin wrapper that knows
 * how to expand the app's `modules/` convention into multi-root pagesDirs /
 * componentsDirs before calling `buildPages()`. The handlr-build package
 * itself is module-agnostic — it just accepts arrays of roots — so module
 * discovery lives here, in the app, where it belongs.
 *
 * Usage (from package.json):
 *
 *     "build": "vite build && node ./scripts/build-pages.mjs"
 *
 * Loads `site.config.js` from the project root, expands `siteConfig.modules`
 * via `resolveModuleDirs`, prepends the app's own `content/pages` and
 * `content/components` (so app-level routes always sort first), and hands the
 * merged arrays to handlr-build.
 */
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { buildPages } from '@phillipsharring/handlr-build/build-pages';
import { resolveModuleDirs } from '@phillipsharring/handlr-build/module-dirs';

async function loadSiteConfig(rootDir) {
    const configPath = path.join(rootDir, 'site.config.js');
    try {
        const mod = await import(pathToFileURL(configPath).href);
        return mod.default || mod;
    } catch (err) {
        if (err && err.code === 'ERR_MODULE_NOT_FOUND') return {};
        throw err;
    }
}

async function main() {
    const rootDir = process.cwd();
    const siteConfig = await loadSiteConfig(rootDir);

    const moduleNames = Array.isArray(siteConfig.modules) ? siteConfig.modules : [];
    const { pagesDirs: modulePagesDirs, componentsDirs: moduleComponentsDirs } =
        resolveModuleDirs(rootDir, moduleNames);

    // App's own roots come first so existing app routes are indexed before any
    // module-contributed routes. Conflict detection in handlr-build will throw
    // if a module tries to claim an app-owned route.
    const pagesDirs = [path.join(rootDir, 'content', 'pages'), ...modulePagesDirs];
    const componentsDirs = [path.join(rootDir, 'content', 'components'), ...moduleComponentsDirs];

    await buildPages({
        root: rootDir,
        siteConfig,
        pagesDirs,
        componentsDirs,
    });
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
