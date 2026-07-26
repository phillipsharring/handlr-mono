# Declarative Behavior

Most interaction in a handlr app is expressed as **attributes on HTML**, not
hand-written JavaScript (ADR 0003). HTMX already makes the *request* declarative; this
layer makes the *response handling* declarative too — what to do after a 2xx, how to
run a small bit of behavior on click, how to log out. It rests on a single delegated
listener per event type, so it keeps working across boosted-nav swaps with nothing to
re-initialize.

## Delegation (the foundation)

Everything is built on event delegation: one listener on `document.body` per event
type, matching by selector. Because it's delegated, dynamically-swapped content is
covered automatically. The escape hatches (from the barrel):

```js
import { onClick, onEvent, onHtmx } from '@phillipsharring/handlr-frontend';

onClick('[data-thing]', (el, event) => { /* el = closest match */ });
onEvent('input', '[data-live]', (el, event) => { … });
onHtmx('htmx:afterRequest', 'form', (el, event) => { … });   // resolves el from event.detail.elt
```

## Named actions — `data-action`

Register a handler under a name; run it from markup with `data-action="name"`:

```js
import { registerAction } from '@phillipsharring/handlr-frontend';
registerAction('archive-toggle', (el, event) => { /* … */ });
```

```html
<button data-action="archive-toggle">Archive</button>
```

`initActions()` (wired by `./init`) delegates `[data-action]` clicks to the registry
and ships two built-ins:

- **`toggle`** — shows/hides the element named by `data-target` (or `data-toggle`),
  flipping Tailwind `hidden` and the trigger's `aria-expanded`. Shorthand:
  `data-toggle="#panel"` runs it without `data-action`.
- **`copy`** — copies `data-copy` (or the element's text) to the clipboard and fires a
  bubbling `handlr:copied` event with `detail.value`.

```html
<button data-toggle="#filters" aria-expanded="false">Filters</button>
<button data-action="copy" data-copy="ABC-123">Copy code</button>
```

## Form responses — `data-on-success` / `data-on-error`

HTMX makes the request; these attributes decide the UI *after* it, on the requesting
element. Each takes a single verb (`verb` or `verb:arg`):

| Verb | Effect |
|---|---|
| `redirect` | navigate to `meta.redirect` (or `data-redirect-url`) |
| `reveal:#sel` | hide the requesting form and un-hide `#sel` |
| `toast:Message` | show a toast with that message |
| `action:name` | run the named action `name` |

```html
<form hx-post="/api/auth/login" hx-ext="json-enc"
      data-on-success="redirect"
      data-on-error="toast:Login failed. Check your details.">
  <!-- fields -->
</form>

<form hx-post="/api/waitlist" hx-ext="json-enc"
      data-on-success="reveal:#thanks">
  <!-- email field -->
</form>
<div id="thanks" class="hidden">You're on the list.</div>
```

`data-on-success` runs on a 2xx, `data-on-error` on anything else. Need more than a
verb? Drop to the imperative hooks, which hand you the parsed JSON:

```js
import { onFormSuccess, onFormError } from '@phillipsharring/handlr-frontend';
onFormSuccess('#invite-form', (data, form, xhr) => { /* … */ });
onFormError('#invite-form', (data, form, xhr) => { /* … */ });
```

## Auth flows

Login, signup, forgot, and reset are just forms using the verbs above
(`data-on-success="redirect"` or `reveal:#success`). Logout is the one preset action —
registered by `initAuthFlows()`:

```html
<a data-action="logout" data-logout-url="/api/auth/logout" data-redirect-url="/">
  Log out
</a>
```

`logout` GETs the logout URL, then hard-navigates to the redirect regardless of the
result (a stale session shouldn't strand the user on a logged-in view).

## Why attributes over scripts

Behavior lives next to the markup it affects, survives DOM swaps for free (delegation),
and needs no per-page wiring. When a case genuinely needs code, you register one named
action or use one imperative hook — a small, named seam — rather than sprinkling
`addEventListener` across the app. The bias, borne out across the apps built on handlr,
is: reach for a built-in HTML/attribute behavior first; write script only when the
attribute layer can't express it.

## See also

- [Runtime](./runtime) — entry points and lifecycle hooks.
- [HTMX Patterns](./htmx) — the requests these responses handle.
- [Modals & Toasts](./ui) — `toast:` and the confirm dialog.
