import htmx from '@phillipsharring/handlr-frontend/src/lib/htmx.js';
import Handlebars from 'handlebars';
import './styles/style.css';
import {
    registerHandlebarsHelpers,
    initCopyIdHandler,
    registerToastHelpers,
    initToastEventHandlers,
    initPagination,
    initTableSort,
    onPageLoad,
    onAfterSwap,
    registerAdminPermissionPrefixes,
    registerAbHelpers,
} from '@phillipsharring/handlr-frontend';

// Boosted navigation defaults to scrolling the swapped content into view.
// That can feel like a "jump" when moving between short/long pages (or using back/forward).
htmx.config.scrollIntoViewOnBoost = false;

window.Handlebars = Handlebars;

// Register Handlebars helpers
registerToastHelpers(Handlebars);
registerHandlebarsHelpers(Handlebars);
registerAbHelpers(Handlebars);

// Import HTMX extensions AFTER globals are set
import '@phillipsharring/handlr-frontend/src/lib/json-enc.js';
import '@phillipsharring/handlr-frontend/src/lib/client-side-templates.js';

// Core infrastructure (self-registering — registers CSRF, boosted-nav, auth-state, forms, search, sortable, hooks)
import '@phillipsharring/handlr-frontend/init';

// Admin permission prefixes — must be registered before DOMContentLoaded fires.
// More specific prefixes first; the framework's checkAdminPermissions() matches
// the first prefix that starts with the current pathname.
registerAdminPermissionPrefixes([
    ['/admin/', 'admin.access'],
]);

// A/B testing (self-registering — fetches assignments via lifecycle hooks)
import './ab.js';

// Assemble window namespace (must be after all framework imports so the
// things namespace.js re-exports are available, and BEFORE the module glob
// below so modules can safely reference window.App at their top level).
import './namespace.js';

// ── Frontend modules ──
// Lazily import every module's module.js, then await them all. We use the
// non-eager form on purpose: { eager: true } would let Vite hoist these
// imports above namespace.js, breaking modules that reference App.* at the
// top level. The await chain below blocks the rest of app.js from running
// until every module has finished its init code, so behavior is identical
// to the eager form for downstream consumers.
//
// Removing a module = deleting its directory under modules/. Adding one =
// creating a directory and adding its name to site.config.modules so the
// build/dev plugin discovers its pages.
const __modules = import.meta.glob('../modules/*/module.js');
await Promise.all(Object.values(__modules).map((load) => load()));

// Copy-to-clipboard button handler
initCopyIdHandler();

// App-level lifecycle hooks
onPageLoad(function(doc) {
    initPagination(doc);
    initTableSort(doc);
    initToastEventHandlers();
});

onAfterSwap(function(target) {
    initPagination(target);
    initTableSort(target);
});
