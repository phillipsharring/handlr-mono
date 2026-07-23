// ---------------------------
// Named action registry (ADR 0003)
// ---------------------------
// Declarative clicks: `data-action="name"` on a clickable element runs the
// handler registered under `name`. The attribute is the *invocation*; the
// registration (registerAction) is the *definition* — two halves of one path,
// not two competing ways (ADR 0003 §D6).
//
//   <button data-action="archive" data-id="…">Archive</button>
//   registerAction('archive', (el, event) => { … })
//
// The registry is a module-scoped Map — it is NOT hung off window.App (ADR 0003
// §D2). Apps that want app-side access re-export deliberately in their namespace.
//
// Escape hatch for behaviors not worth naming: onClick(selector, fn) (delegation.js).

import { onClick } from './delegation.js';

const registry = new Map();

/**
 * Register (or replace) a named action. Invoked by `data-action="name"` clicks
 * once initActions() has wired the delegated listener, or directly via runAction.
 * @param {string} name
 * @param {(el: Element, event: Event|null) => void} handler
 */
export function registerAction(name, handler) {
    if (typeof name !== 'string' || !name) {
        throw new TypeError('registerAction: name must be a non-empty string');
    }
    if (typeof handler !== 'function') {
        throw new TypeError('registerAction: handler must be a function');
    }
    registry.set(name, handler);
}

/** Alias for registerAction — reads better at some call sites. */
export const onAction = registerAction;

/** Look up a registered action handler (or undefined). */
export function getAction(name) {
    return registry.get(name);
}

/** Whether an action is registered. */
export function hasAction(name) {
    return registry.has(name);
}

/**
 * Run a registered action by name. Returns true if an action was found and run,
 * false otherwise. Safe to call with an unknown/empty name.
 */
export function runAction(name, el, event = null) {
    if (!name) return false;
    const handler = registry.get(name);
    if (!handler) return false;
    handler(el, event);
    return true;
}

// ── Built-in actions ──

/**
 * `toggle` — show/hide the target(s). Target selector comes from `data-target`,
 * or from `data-toggle` when used via the `data-toggle="#sel"` shorthand.
 * Toggles the Tailwind `hidden` class (matches existing app markup) and flips
 * `aria-expanded` on the trigger when present.
 */
export function toggleAction(el) {
    const selector = el.getAttribute('data-target') || el.getAttribute('data-toggle');
    if (!selector) return;
    document.querySelectorAll(selector).forEach((target) => {
        target.classList.toggle('hidden');
    });
    const expanded = el.getAttribute('aria-expanded');
    if (expanded !== null) {
        el.setAttribute('aria-expanded', expanded === 'true' ? 'false' : 'true');
    }
}

/**
 * `copy` — copy `data-copy` (or the element's text) to the clipboard. Supersedes
 * the bespoke initCopyIdHandler(). Fires a bubbling `handlr:copied` event on
 * success so callers can show feedback. No-op when the Clipboard API is absent.
 */
export function copyAction(el) {
    const value = el.getAttribute('data-copy') ?? (el.textContent ? el.textContent.trim() : '');
    if (!value) return;
    if (typeof navigator === 'undefined' || !navigator.clipboard) return;
    return navigator.clipboard.writeText(value).then(() => {
        if (typeof CustomEvent !== 'undefined' && typeof el.dispatchEvent === 'function') {
            el.dispatchEvent(new CustomEvent('handlr:copied', { bubbles: true, detail: { value } }));
        }
    }).catch(() => {});
}

let builtinsRegistered = false;
let listenersWired = false;

function registerBuiltins() {
    if (builtinsRegistered) return;
    builtinsRegistered = true;
    registerAction('toggle', toggleAction);
    registerAction('copy', copyAction);
}

/**
 * Wire the delegated `data-action` / `data-toggle` click listeners and register
 * the built-in actions. Idempotent — safe to call more than once. Called by
 * `./init`; also callable à la carte.
 */
export function initActions() {
    registerBuiltins();
    if (listenersWired) return;
    listenersWired = true;
    onClick('[data-action]', (el, event) => {
        runAction(el.getAttribute('data-action'), el, event);
    });
    // Shorthand: data-toggle="#sel" runs the toggle action without data-action.
    onClick('[data-toggle]', (el, event) => {
        runAction('toggle', el, event);
    });
}
