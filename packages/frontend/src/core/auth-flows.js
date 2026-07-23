// ---------------------------
// Auth-flow preset (ADR 0003, pass C)
// ---------------------------
// The recurring auth flows are expressed with the pass-1 primitives — no new
// verbs needed:
//
//   login / signup  →  data-on-success="redirect"          (meta.redirect)
//   forgot / reset  →  data-on-success="reveal:#success"   (hide form, show node)
//
// The one flow an attribute can't express cleanly is logout (fire a request, then
// hard-navigate home regardless of outcome). It's a named domain concept that
// recurs in several markup variants today, so it becomes a built-in `logout`
// action registered by this opt-in preset.
//
// Uses plain fetch (not apiFetch) on purpose: logout is GET (no CSRF needed), and
// keeping this module free of the csrf/fetch-client import chain leaves it
// side-effect-free on import. When loaded via ./init, csrf.js has already patched
// window.fetch anyway.

import { registerAction } from './actions.js';

/**
 * `logout` — GET the logout endpoint, then hard-navigate home. Best-effort:
 * navigates whether the request succeeds or fails. Suppresses the element's
 * default navigation so an <a href> can double as a no-JS fallback.
 *
 * Endpoint / destination override via `data-logout-url` / `data-redirect-url`.
 */
export function logoutAction(el, event) {
    if (event && typeof event.preventDefault === 'function') event.preventDefault();
    const url = (el && el.getAttribute && el.getAttribute('data-logout-url')) || '/api/auth/logout';
    const home = (el && el.getAttribute && el.getAttribute('data-redirect-url')) || '/';
    const go = () => { window.location.href = home; };
    try {
        return fetch(url, { method: 'GET', credentials: 'same-origin' }).then(go, go);
    } catch {
        go();
        return undefined;
    }
}

let registered = false;

/**
 * Register the auth-flow built-ins (currently just `logout`). Idempotent — safe
 * to call more than once. Called by `./init` (auth is part of the default
 * batteries); also callable à la carte.
 */
export function initAuthFlows() {
    if (registered) return;
    registered = true;
    registerAction('logout', logoutAction);
}
