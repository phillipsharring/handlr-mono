// ---------------------------
// Form-response branching (ADR 0003)
// ---------------------------
// HTMX handles the request; this layer handles what happens to the UI *after* a
// 2xx, driven by attributes on the requesting element:
//
//   <form hx-post="/api/auth/login"  data-on-success="redirect">…</form>
//   <form hx-post="/api/auth/forgot" data-on-success="reveal:#forgot-success">…</form>
//   <form hx-post="/api/things"      data-on-success="toast:Saved">…</form>
//
// Verbs (single verb per attribute — ADR 0003 §D1):
//   redirect        → navigate to meta.redirect (fallback: data-redirect-url)
//   reveal:#sel     → hide the requesting form AND reveal #sel (one atomic op)
//   toast:Message   → show a toast
//   action:name     → run a registered action (bridge into the imperative side)
//
// `data-on-error` runs the same verb grammar on a non-2xx response.
//
// Imperative escape hatches — onFormSuccess / onFormError — hand back parsed JSON
// so callers stop re-writing the try/parse dance.

import { onHtmx } from './delegation.js';
import { HandlrToast } from '../ui/toast.js';
import { runAction } from './actions.js';

/** Parse "verb:arg" → { name, arg }. No colon ⇒ arg is null. */
export function parseVerb(spec) {
    if (typeof spec !== 'string') return { name: '', arg: null };
    const trimmed = spec.trim();
    const idx = trimmed.indexOf(':');
    if (idx === -1) return { name: trimmed, arg: null };
    return {
        name: trimmed.slice(0, idx).trim(),
        arg: trimmed.slice(idx + 1).trim() || null,
    };
}

/** True on a 2xx (by xhr.status), falling back to htmx's detail.successful. */
export function isRequestSuccess(detail) {
    const status = typeof detail?.xhr?.status === 'number' ? detail.xhr.status : null;
    if (status !== null) return status >= 200 && status < 300;
    return !!detail?.successful;
}

/** Parse an xhr's JSON body; return null on any failure (never throws). */
export function safeParseJson(xhr) {
    try {
        return JSON.parse(xhr?.responseText || '');
    } catch {
        return null;
    }
}

/** Redirect URL from response meta, falling back to data-redirect-url. */
export function resolveRedirectUrl(el, data) {
    return (data && data.meta && data.meta.redirect) || el.getAttribute('data-redirect-url') || null;
}

function navigate(url) {
    window.location.href = url;
}

/**
 * Execute one verb spec against the requesting element. Shared by success and
 * error paths. Unknown verbs are a no-op.
 */
export function runVerb(spec, el, xhr) {
    const { name, arg } = parseVerb(spec);
    if (!name) return;
    const data = safeParseJson(xhr);
    const form = (el.closest && el.closest('form')) || el;

    switch (name) {
        case 'redirect': {
            const url = resolveRedirectUrl(el, data);
            if (url) navigate(url);
            break;
        }
        case 'reveal': {
            if (form && form.classList) form.classList.add('hidden');
            if (arg) {
                const target = document.querySelector(arg);
                if (target) target.classList.remove('hidden');
            }
            break;
        }
        case 'toast': {
            if (arg) HandlrToast.show({ message: arg });
            break;
        }
        case 'action': {
            if (arg) runAction(arg, el, null);
            break;
        }
        default:
            break;
    }
}

let listenersWired = false;

/**
 * Wire the delegated data-on-success / data-on-error handlers. Idempotent —
 * safe to call more than once. Called by `./init`; also callable à la carte.
 */
export function initFormResponses() {
    if (listenersWired) return;
    listenersWired = true;

    onHtmx('htmx:afterRequest', '[data-on-success]', (el, event) => {
        const detail = event.detail || {};
        if (!isRequestSuccess(detail)) return;
        runVerb(el.getAttribute('data-on-success'), el, detail.xhr);
    });

    onHtmx('htmx:afterRequest', '[data-on-error]', (el, event) => {
        const detail = event.detail || {};
        if (isRequestSuccess(detail)) return;
        runVerb(el.getAttribute('data-on-error'), el, detail.xhr);
    });
}

/**
 * Imperative: run `handler(data, form, xhr)` after a successful request from an
 * element matching `selector`. `data` is the eagerly-parsed JSON body (null on a
 * non-JSON / unparseable body — never throws). `form` is the closest form (or the
 * element itself). Escape hatch for logic an attribute can't express.
 */
export function onFormSuccess(selector, handler) {
    onHtmx('htmx:afterRequest', selector, (el, event) => {
        const detail = event.detail || {};
        if (!isRequestSuccess(detail)) return;
        const form = (el.closest && el.closest('form')) || el;
        handler(safeParseJson(detail.xhr), form, detail.xhr);
    });
}

/** Imperative counterpart to onFormSuccess for non-2xx responses. */
export function onFormError(selector, handler) {
    onHtmx('htmx:afterRequest', selector, (el, event) => {
        const detail = event.detail || {};
        if (isRequestSuccess(detail)) return;
        const form = (el.closest && el.closest('form')) || el;
        handler(safeParseJson(detail.xhr), form, detail.xhr);
    });
}
