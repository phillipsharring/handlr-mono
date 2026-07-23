# Changelog

## 0.11.0

Adopt the handlr-frontend declarative behavior layer (ADR 0003). The hand-rolled
auth-flow scripts are gone — the markup is now declarative.

### Changed

- **login / signup** forms use `data-on-success="redirect"`; their inline
  `htmx:afterRequest` → parse-JSON → `meta.redirect` scripts were deleted.
- **forgot-password / reset-password** forms use `data-on-success="reveal:#…-success"`;
  their inline hide-form/show-success scripts were deleted (reset keeps only its
  token-setup script). Relies on the backend returning non-2xx on failure (it does —
  422), so `reveal` fires only on real success.
- **logout** links (admin layout + `auth/auth-links.html`) use
  `data-action="logout" hx-boost="false"`; the `hx-get` / `hx-on::after-request` and the
  inline `logout-link` redirect scripts (admin layout + `auth/login-modal.html`) were removed.

### Note

- Requires `@phillipsharring/handlr-frontend` 0.11.0+ (the behavior layer). The dependency
  pin is bumped as part of the lockstep release.

## 0.2.0

### Updated
- **Handlr Framework 0.2.0**  - adds lifecycle hooks (`onAfterSwap`, `onAfterSettle`, `onPageLoad`, `onHistoryRestore`). Use these instead of raw `htmx:afterSwap` listeners.

#### How to use hooks

From `app.js` or ES module code:
```js
import { onAfterSwap, onPageLoad } from '@phillipsharring/handlr-frontend';

onAfterSwap(function(target) {
    // Runs after #app is swapped via boosted nav.
    // `target` is the new #app element.
    initMyWidget(target);
});

onPageLoad(function(doc) {
    // Runs on DOMContentLoaded (full page load only).
    initMyWidget(doc);
});
```

From inline page scripts (via namespace):
```js
App.hooks.onAfterSwap(function(target) { ... });
```

To expose hooks on the namespace, add to `namespace.js`:
```js
import { onAfterSwap, onAfterSettle, onPageLoad, onHistoryRestore } from '@phillipsharring/handlr-frontend';

window.App = {
    // ...existing namespace...
    hooks: { onAfterSwap, onAfterSettle, onPageLoad, onHistoryRestore },
};
```

## 0.1.0

Initial release. Auth system (login, signup, logout, RBAC), CSRF protection, base layout with modal/toast/confirm components, login and signup pages, seeders for admin user and role.
