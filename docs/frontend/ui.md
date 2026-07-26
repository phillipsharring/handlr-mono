# Modals & Toasts

The runtime ships a single global modal, a modal-form helper, a confirm dialog, and a
toast system. All operate on well-known element ids that the app skeleton includes in
its layout (`#global-modal`, `#global-toast-content`, and their templates).

## The global modal

There is one modal in the DOM; you render content into it and open it.

```js
import { openGlobalModal, closeGlobalModal, isGlobalModalOpen } from '@phillipsharring/handlr-frontend';
```

It manages focus (restoring it on close), locks body scroll, sets `aria-hidden`, and
dispatches a `modal:closed` event. It closes on `Escape` and on a backdrop click
(unless opened in `takeover` mode). Sizes are `sm` / `lg` / `takeover` (default).

You rarely open it empty — the two helpers below cover the common cases. HTMX can also
open it: a swap into `#global-modal-content` opens the modal automatically.

## Modal forms — `openFormModal`

Render a `<template>` into the modal as a working form:

```js
import { openFormModal } from '@phillipsharring/handlr-frontend';

openFormModal({
    templateId: 'new-checklist-template',   // id of a <template> (no '#')
    title: 'New checklist',
    size: 'sm',
    formUrl: '/api/checklists',
    formMethod: 'post',                     // 'post' | 'patch' — required to set hx-*
    fields: { name: '' },                   // populate inputs by name attribute
});
```

| Option | Purpose |
|---|---|
| `templateId` | the `<template>` to render (required) |
| `title` | modal header |
| `formUrl` | sets the form `action` + `hx-post`/`hx-patch` |
| `formMethod` | `'post'` or `'patch'` — **required** for `formUrl` to set the `hx-*` attribute |
| `fields` | `{ name: value }` — fills inputs/selects/textareas by `name` |
| `size` | `'sm'` \| `'lg'` \| `'takeover'` \| `'default'` |
| `removeFields`, `submitButtonText`, `focusSelector`, `beforeOpen` | fine-tuning |

The template is compiled through Handlebars first, so partials like the
<code v-pre>{{> formButtons}}</code> partial resolve. On a successful submit from inside the modal, the runtime
closes it and fires any `data-refresh-target` / `data-refresh-event` you set (or honors
a `meta.redirect`).

## Confirm dialog — `HandlrConfirm`

For destructive actions, confirm first. Programmatic:

```js
import { HandlrConfirm } from '@phillipsharring/handlr-frontend';

const ok = await HandlrConfirm.open({
    message: 'Delete this checklist?',
    subtext: 'This cannot be undone.',
    confirmText: 'Delete',
    onConfirm: async () => { /* runs before resolving; keeps modal open if it throws */ },
});
```

Or fully declarative — no JS — with `data-confirm-*` on the trigger:

```html
<button data-confirm
        data-confirm-message="Delete {count} items?"
        data-confirm-confirm-text="Delete"
        data-confirm-request-method="delete"
        data-confirm-request-url="/api/checklists/abc"
        data-confirm-refresh-target="#checklists">
  Delete
</button>
```

The declarative form can issue the request itself (`data-confirm-request-*`), refresh a
target, dispatch an event (`data-confirm-event`), run a batched progress loop
(`data-confirm-progress-url`), or gate on a checkbox (`data-confirm-checkbox-label`).
Non-`confirm*` `data-*` keys on the trigger become the request payload.

> [!TIP]
> Prefer the framework's confirm over a hand-rolled `window.confirm` or a bespoke
> dialog — it's accessible, styled, and consistent. Use the declarative `data-confirm-*`
> form when the trigger already knows the request; use `HandlrConfirm.open()` when you
> need to await the decision in code.

## Toasts — `HandlrToast`

```js
import { HandlrToast } from '@phillipsharring/handlr-frontend';
HandlrToast.show({ message: 'Saved.', status: 'success' });   // success | warning | error
```

Toasts auto-dismiss (5s default). They're also raised declaratively via the
`toast:Message` [form-response verb](./declarative#form-responses-data-on-success-data-on-error),
and automatically when HTMX swaps content into `#global-toast-content`. Call
`registerToastHelpers(Handlebars)` in your app entry so the toast template's
`toastClass` helper is available.

## Accessibility

The modal traps and restores focus, sets `aria-hidden` on the background, and closes on
`Escape`; confirm/toast content is keyboard reachable. Keep meaning off color alone
(pair the toast color with its text), and honor `prefers-reduced-motion` in your own
transitions.

## See also

- [Declarative Behavior](./declarative) — `toast:` and action verbs.
- [HTMX Patterns](./htmx) — opening the modal from an HTMX swap.
