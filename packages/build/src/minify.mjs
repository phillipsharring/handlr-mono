/**
 * HTML minification for baked pages, backed by the optional `html-minifier-terser`
 * peer dependency. Kept in its own module so the dynamic import and the default
 * option set live in one place, and so the dep is only loaded when minify is on.
 */

/**
 * Matches the comment `[[gitSha]]` renders, e.g. `<!-- 37627d2 -->`: nothing but a
 * short or full commit hash. Without it `removeComments` strips the deployed
 * version out of the page that stamps it.
 */
export const GIT_SHA_COMMENT = /^\s*[0-9a-f]{7,40}\s*$/;

// Matches the option set the pre-graspr phillipharrington.com build used, plus
// the [[gitSha]] exception. `ignoreCustomComments` repeats html-minifier-terser's
// own defaults (`<!--! ... -->` and `<!-- # ... -->`) because setting the option
// replaces that list rather than adding to it.
const DEFAULT_MINIFY_OPTIONS = {
    removeAttributeQuotes: true,
    collapseWhitespace: true,
    removeComments: true,
    removeRedundantAttributes: true,
    removeScriptTypeAttributes: true,
    removeTagWhitespace: true,
    ignoreCustomComments: [/^!/, /^\s*#/, GIT_SHA_COMMENT],
};

/**
 * Normalize the `minify` option into a concrete options object, or `null` when
 * the feature is off (the default).
 *
 * - `false`/`undefined`   -> null (no minification)
 * - `true`                -> the defaults above
 * - `{ ...overrides }`    -> defaults shallow-merged with the overrides (so an
 *                            `ignoreCustomComments` override replaces the default list)
 *
 * @param {boolean|object} [opt]
 * @returns {object | null}
 */
export function resolveMinifyOptions(opt) {
    if (!opt) return null;
    // Copy the list so a caller mutating its resolved options can't change the defaults.
    const defaults = {
        ...DEFAULT_MINIFY_OPTIONS,
        ignoreCustomComments: [...DEFAULT_MINIFY_OPTIONS.ignoreCustomComments],
    };
    if (opt === true) return defaults;
    return { ...defaults, ...opt };
}

/**
 * Resolve a minify function for the given option, or `null` when minify is off.
 * Loads `html-minifier-terser` lazily — it's an optional peer dependency, so
 * consumers who don't minify never need it installed. Throws a clear, actionable
 * error if minify is requested but the dep isn't present.
 *
 * The `load` parameter is an injection seam for tests; production callers use
 * the default dynamic import.
 *
 * @param {boolean|object} [opt]
 * @param {() => Promise<{minify: Function, default?: {minify: Function}}>} [load]
 * @returns {Promise<((html: string) => Promise<string>) | null>}
 */
export async function createHtmlMinifier(opt, load = () => import('html-minifier-terser')) {
    const options = resolveMinifyOptions(opt);
    if (!options) return null;

    let mod;
    try {
        mod = await load();
    } catch {
        throw new Error(
            'buildPages({ minify: true }) requires the html-minifier-terser peer dependency. ' +
            'Install it: npm i -D html-minifier-terser'
        );
    }

    const minify = mod.minify ?? mod.default?.minify;
    if (typeof minify !== 'function') {
        throw new Error(
            'html-minifier-terser was found but did not export a minify() function. ' +
            'Expected version ^7.0.0.'
        );
    }

    return (html) => minify(html, options);
}
