// Minimal zero-dependency DOM stubs for unit tests. Just enough surface for the
// declarative behavior layer — not a real DOM. Only what the code under test
// touches: getAttribute, classList, closest('form'), dispatchEvent, and a fake
// document.body that captures delegated listeners so tests can emit events.

export function classList(initial = []) {
    const set = new Set(initial);
    return {
        add: (c) => { set.add(c); },
        remove: (c) => { set.delete(c); },
        toggle: (c) => (set.has(c) ? (set.delete(c), false) : (set.add(c), true)),
        contains: (c) => set.has(c),
        _set: set,
    };
}

/** A stub element. `form` is what closest('form') returns. */
export function el({ attrs = {}, text = '', classes = [], form = null } = {}) {
    return {
        _attrs: { ...attrs },
        textContent: text,
        classList: classList(classes),
        _form: form,
        _dispatched: [],
        getAttribute(name) { return name in this._attrs ? this._attrs[name] : null; },
        setAttribute(name, value) { this._attrs[name] = String(value); },
        closest(sel) { return sel === 'form' ? this._form : null; },
        dispatchEvent(evt) { this._dispatched.push(evt); return true; },
    };
}

/** A fake body that records delegated listeners; _emit fires them. */
export function fakeBody() {
    const listeners = {};
    return {
        addEventListener(type, fn) { (listeners[type] || (listeners[type] = [])).push(fn); },
        _emit(type, event) { (listeners[type] || []).forEach((fn) => fn(event)); },
    };
}

/**
 * Install a fake global `document` (with the given body) plus optional
 * querySelector/querySelectorAll/getElementById lookups. Returns a restore fn.
 */
export function installDocument({ body = fakeBody(), query = {}, queryAll = {}, byId = {} } = {}) {
    const prevDoc = globalThis.document;
    globalThis.document = {
        body,
        querySelector: (sel) => (sel in query ? query[sel] : null),
        querySelectorAll: (sel) => (sel in queryAll ? queryAll[sel] : []),
        getElementById: (id) => (id in byId ? byId[id] : null),
    };
    return () => { globalThis.document = prevDoc; };
}

/** A click-style event whose target.closest(selector) resolves to `match`. */
export function clickEvent(selector, match) {
    return { target: { closest: (s) => (s === selector ? match : null) } };
}

/** An htmx-style event: detail.elt.closest(selector) resolves to `match`. */
export function htmxEvent(selector, match, detail = {}) {
    return { detail: { ...detail, elt: { closest: (s) => (s === selector ? match : null) } } };
}
