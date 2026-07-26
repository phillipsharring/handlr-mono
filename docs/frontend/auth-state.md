# Auth State

The frontend keeps a small, cached view of "who's logged in" and uses it to show or
hide UI, gate admin areas, and intercept expired sessions — all without a round-trip
per element. It's wired by `./init` (`core/auth-state.js`); you drive it with
attributes.

## The cached auth check

One request per page load answers the auth question, cached for everything that asks:

```js
import { getAuthData, checkAuth, refreshAuthData } from '@phillipsharring/handlr-frontend';

await checkAuth();        // boolean
await getAuthData();      // { authenticated, username, permissions[] } — cached
await refreshAuthData();  // invalidate + re-fetch (call after login/logout)
```

`getAuthData()` maps `GET /api/auth/me` to `{ authenticated, username, permissions }`;
a failure resolves to a safe logged-out shape.

## Auth-gated UI

Mark elements with data attributes; the layer reveals the right ones once auth
resolves:

| Attribute | Behavior |
|---|---|
| `data-auth-login` / `data-auth-logout` | start hidden; the matching one is revealed by auth state (username written into `#user-menu-username`) |
| `data-show-if-auth` | revealed when authenticated |
| `data-hide-if-auth` | revealed when **not** authenticated |
| `data-requires-auth` | when authenticated, fires an `auth-load` event on the element (once) |
| `data-requires-permission="perm"` | revealed only if the user holds `perm` |

`data-requires-auth` is the key pattern for **self-loading, auth-gated content**: pair
it with `hx-trigger="auth-load"` so the element fetches its data only after auth is
confirmed (avoiding an anonymous request that would 401):

```html
<div hx-get="/api/widgets" hx-trigger="auth-load, refresh"
     hx-target="this" hx-select="unset" hx-swap="innerHTML"
     handlebars-array-template="widget-tpl" data-requires-auth></div>
```

The `auth-load` trigger fires on `htmx:afterSettle` (not `afterSwap`), so the element's
`hx-trigger` is wired before the event is dispatched.

## Permission gating in the DOM

`data-requires-permission` reveals an element only when the cached permissions include
the named permission. For **admin areas gated by URL prefix**, register the mapping
once:

```js
import { registerAdminPermissionPrefixes } from '@phillipsharring/handlr-frontend';
registerAdminPermissionPrefixes([
    ['/admin/billing/', 'billing.manage'],   // most-specific first
    ['/admin/',         'admin.access'],
]);
```

Navigating under a prefix without the permission redirects the user away.

## Login modal & 401 / 403 handling

The layer intercepts HTMX responses (on `htmx:beforeSwap`, capture phase) so an
expired session never renders a broken page:

- **`401`** — suppress the swap and open the login modal
  (`openFormModal({ templateId: 'login-form-template', … })`). This is why
  [`RequireAuthPipe`](/backend/auth) returns a `401` JSON instead of redirecting —
  the client decides what to show.
- **`403`** — suppress the swap and show a "session expired" warning toast (the fresh
  [CSRF token](./csrf) is already captured from the rotated cookie), so the user can
  retry.

## Keeping in sync with the backend

After a login or logout, the server session changed but the cached auth data hasn't.
Resync by dispatching `auth-refresh` on `document.body` (the layer listens for it and
re-runs `refreshAuthData()` → re-applies state), or call `refreshAuthData()` directly.
A header widget that lives **outside** `#app` (so it isn't replaced on boosted swaps)
can carry `data-header-widget` to be refreshed after each `#app` swap.

## See also

- [CSRF](./csrf) — token rotation and the 403 recovery this layer performs.
- [Auth](/backend/auth) — the session pipes and the 401 contract.
- [Declarative Behavior](./declarative) — `data-action="logout"` and the auth-flow verbs.
