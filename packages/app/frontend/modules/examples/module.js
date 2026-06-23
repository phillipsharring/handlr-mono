// Examples module — frontend side.
//
// `import.meta.glob('../modules/*/module.js', { eager: true })` in src/app.js
// pulls this file into the bundle, so anything below runs once at startup.
//
// What this module owns:
//   1. The /examples/* pages under modules/examples/pages/
//   2. The <callout> build-time component under modules/examples/components/
//   3. The bits of behavior the example pages exercise (delegated handlers,
//      click-burst init on swap)
//
// Removing the module: delete this directory and remove 'examples' from
// site.config.modules. Nothing else references it.

// ── Delegated `data-action="ping"` button (sec 2 of /examples) ──
// One document-level listener so it survives every HTMX swap without
// per-page re-init. Demonstrates that plain JS hooks keep working after
// boosted-nav swaps.
document.addEventListener('click', (e) => {
    const btn = e.target.closest("[data-action='ping']");
    if (!btn) return;

    alert('pong ✅ (delegated binding survives swaps)');
});

// ── Click-burst init (sec 9 of /examples) ──
// initClickBurst() walks the DOM for [data-click-burst] elements and attaches
// per-element listeners (idempotent — re-runs are safe). Needs to fire on
// initial page load AND after every HTMX swap, since swapped-in content
// brings new elements with the attribute.
App.hooks.onPageLoad(() => App.ui.clickBurst.init(document));
App.hooks.onAfterSwap((target) => App.ui.clickBurst.init(target));
