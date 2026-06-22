/**
 * Site-wide configuration. Injected into layouts via [[propName]] placeholders.
 *
 * `modules` is an optional array of module objects (from npm packages) or
 * strings (local directory names under `modules/`). Modules can contribute
 * pages and components to the build.
 */
export default {
    siteUrl: '/',
    siteName: 'My Site',
    copyright: '&copy; My Site. All rights reserved.',
    modules: [],
};
