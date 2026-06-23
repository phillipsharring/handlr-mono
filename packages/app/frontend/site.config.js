/**
 * Site-wide configuration.
 * These values are injected into layouts via [[propName]] placeholders.
 *
 * `modules` is the list of frontend modules to enable. Each entry is either
 * a module object (from an npm package) or a string (local directory name
 * under `modules/`). See `@phillipsharring/handlr-build/modules` for the
 * API. Order doesn't affect routing — handlr-build merges all roots and
 * errors loudly on cross-root route conflicts.
 */
export default {
    siteUrl: '/',
    siteName: 'My App',
    copyright: '© My App. All Rights Reserved',
    modules: ['examples'],
};
