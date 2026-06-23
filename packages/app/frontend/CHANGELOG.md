# Changelog

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
