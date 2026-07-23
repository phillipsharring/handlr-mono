// ---------------------------
// Event delegation core (ADR 0003)
// ---------------------------
// One delegated body-level listener per event type, selector-driven. This is the
// shared primitive under the declarative behavior layer (actions, form-response)
// and the imperative escape hatches (onClick / onHtmx).
//
// Why one listener per event type instead of per-element listeners:
//   - Boosted nav replaces everything inside #app on every swap. Per-element
//     listeners on those nodes leak / must be re-added (frontend CLAUDE.md
//     pitfall #6). A single listener on document.body survives swaps and matches
//     freshly-swapped elements for free.
//
// document.body is assumed present when these run — same assumption csrf.js /
// forms.js already make (they attach body listeners at import).

const registries = new Map(); // eventType -> { entries: [{selector, handler}], resolveTarget }

function attach(eventType, resolveTarget) {
    let reg = registries.get(eventType);
    if (reg) return reg;
    reg = { entries: [], resolveTarget };
    registries.set(eventType, reg);
    document.body.addEventListener(eventType, (event) => dispatch(eventType, event));
    return reg;
}

function dispatch(eventType, event) {
    const reg = registries.get(eventType);
    if (!reg) return;
    const root = reg.resolveTarget(event);
    if (!root || typeof root.closest !== 'function') return;
    // Snapshot: a handler may register more handlers mid-dispatch.
    for (const { selector, handler } of reg.entries.slice()) {
        const match = root.closest(selector);
        if (match) handler(match, event);
    }
}

/**
 * Delegated DOM-event binding. The handler receives (matchedElement, event),
 * where matchedElement is the nearest ancestor of the event target matching
 * `selector`. Use for real DOM events (click, change, …).
 */
export function onEvent(eventType, selector, handler) {
    attach(eventType, (e) => e.target).entries.push({ selector, handler });
}

/** Delegated click. Sugar over onEvent('click', …). */
export function onClick(selector, handler) {
    onEvent('click', selector, handler);
}

/**
 * Delegated HTMX event. Matches against the element that issued the request
 * (`event.detail.elt`), falling back to `event.target`. Handler receives
 * (matchedElement, event). Use for htmx:* events (afterRequest, afterSettle, …).
 */
export function onHtmx(eventType, selector, handler) {
    attach(eventType, (e) => e.detail?.elt || e.target).entries.push({ selector, handler });
}

/** Test-only: drop all registrations so a fresh test starts clean. */
export function __resetDelegationForTest() {
    registries.clear();
}
