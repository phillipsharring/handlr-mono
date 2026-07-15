# Handlr Frontend

![Handlr](handlr.png)

The frontend runtime for building server-driven web applications with **HTMX + Handlebars + Tailwind CSS**.

`@phillipsharring/handlr-frontend` handles the hard parts of HTMX-based apps: boosted navigation, auth-gated widgets, modal/toast systems, CSRF token management, form error handling, and client-side template rendering  - so you can focus on your pages and domain logic.

It is the runtime companion to [`@phillipsharring/handlr-build`](https://github.com/phillipsharring/handlr-mono/tree/main/packages/build) (build tooling) and [`phillipsharring/handlr-backend`](https://github.com/phillipsharring/handlr-mono/tree/main/packages/backend) (the PHP backend).

📖 **Documentation:** https://phillipsharring.github.io/handlr-mono/frontend/

## Installation

```bash
npm install @phillipsharring/handlr-frontend
```

Peer dependencies (your app must install these):
```bash
npm install htmx.org handlebars sortablejs
```

For a ready-to-go project structure, scaffold a full app with `composer create-project phillipsharring/handlr-app` (see the [app skeleton](https://github.com/phillipsharring/handlr-mono/tree/main/packages/app)).

## What's Included

### Core Infrastructure (`core/`)
- **boosted-nav**  - fixes HTMX boosted navigation edge cases (inherited targets, `hx-select` overrides, layout mismatch detection)
- **csrf**  - global `fetch()` interceptor + HTMX hook for automatic CSRF token headers
- **auth-state**  - auth-gated UI orchestration (`auth-load` events, permission gating, login modal, 401/403 handling)
- **forms**  - inline form error display, modal form lifecycle, success redirect/refresh patterns
- **pagination**  - paginated table controls with URL param syncing
- **search**  - debounced search input with HTMX integration
- **sortable**  - drag-and-drop reordering via SortableJS wrapper
- **table-sort**  - clickable column header sorting with URL persistence
- **navigation**  - URL helpers, active nav highlighting

### UI Widgets (`ui/`)
- **modal**  - global modal state machine with focus management and overlay/escape handling
- **modal-form**  - modal form populator (set fields, method, clear errors, focus)
- **toast**  - toast notification system with auto-dismiss
- **confirm-dialog**  - confirmation dialog with optional progress mode for batch operations
- **typeahead**  - autocomplete widget factory with keyboard navigation
- **click-burst**  - visual click feedback animation

### HTMX Extensions (`lib/`)
- **json-enc**  - JSON encoding extension for HTMX requests
- **client-side-templates**  - Handlebars template rendering for HTMX JSON responses

### Helpers (`helpers/`)
- **handlebars-helpers**  - generic Handlebars helpers (eq, neq, and, or, truncate, timeAgo, formatDateTime, json, treeIndent, etc.)
- **escape-html**  - HTML escape utility
- **populate-select**  - `<select>` field populator
- **route-params**  - URL parameter extraction for dynamic routes (`[id]` patterns)
- **debounce**  - debounce utility + search input sanitization

### Auth (`auth.js`)
- Single `/api/auth/me` call per page load, cached
- `checkAuth()`  - returns `Promise<boolean>`
- `getAuthData()`  - returns full auth response with permissions
- `refreshAuthData()`  - invalidates cache and re-fetches

### API Client (`fetch-client.js`)
- `apiFetch(url, options)`  - wraps `fetch()` with CSRF headers, JSON content type, body serialization

### Styles (`styles/base.css`)
- Form error styles, HTMX request dimming, modal/takeover animations, sortable drag-and-drop, table sort headers, confirm dialog, active nav highlighting

## Usage

### Import everything at once
```js
import {
    HandlrToast, openFormModal, HandlrConfirm,
    initPagination, initTableSort,
    getRouteParams, escapeHtml,
} from '@phillipsharring/handlr-frontend';
```

### Side-effect initialization
```js
// Registers CSRF interceptors, boosted-nav handlers, auth-state listeners,
// form error handling, search, and sortable  - in the correct order.
import '@phillipsharring/handlr-frontend/init';
```

### HTMX extensions (import after setting window.Handlebars)
```js
import '@phillipsharring/handlr-frontend/src/lib/json-enc.js';
import '@phillipsharring/handlr-frontend/src/lib/client-side-templates.js';
```

### Styles
```css
@import 'tailwindcss';
@source "../../content/**/*.html";
@source "../**/*.js";
@import '@phillipsharring/handlr-frontend/styles/base.css';
```

### Configurable auth permissions
```js
import { registerAdminPermissionPrefixes } from '@phillipsharring/handlr-frontend';

registerAdminPermissionPrefixes([
    ['/admin/design/', 'design.access'],
    ['/admin/story/', 'story.access'],
    ['/admin/', 'admin.access'],
]);
```

## Designed For

Handlr Frontend is the companion to [Handlr Backend](https://github.com/phillipsharring/handlr-mono/tree/main/packages/backend) (PHP), but works with any backend that serves JSON APIs and HTML pages. The auth system expects a `/api/auth/me` endpoint; everything else is configurable.

## Requirements

- Vite 7+
- Tailwind CSS 4+
- Node.js 18+

## License

MIT
