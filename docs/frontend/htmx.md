# HTMX Patterns

The runtime speaks JSON to a handlr API and renders it with Handlebars on the client.
This page collects the recurring recipes. Enable the two extensions on `<body>` once:

```html
<body hx-ext="json-enc, client-side-templates">
```

## Form submission with json-enc

`json-enc` sends the form as a JSON body (matching what handlr handlers expect):

```html
<form hx-post="/api/checklists" hx-ext="json-enc"
      hx-target="#checklists" hx-swap="beforeend"
      data-on-success="toast:Checklist created.">
  <input name="name" required>
  <button type="submit">Create</button>
</form>
```

Pair it with the [declarative response verbs](./declarative#form-responses-data-on-success-data-on-error)
(`data-on-success` / `data-on-error`) to handle the result without JavaScript.

## Rendering API responses with client-side templates

An endpoint returns the standard [envelope](/backend/api-responses); a `<template>` on
the page renders it. Two flavours:

### A single object

```html
<div hx-get="/api/checklists/abc" hx-trigger="load"
     handlebars-template="checklist-tpl"></div>

<template id="checklist-tpl">
  <h1>{{name}}</h1>
  <p>{{data.item_count}} items</p>
</template>
```

The object template spreads `data.data` into scope, then overlays the top-level
envelope so <code v-pre>{{status}}</code>/<code v-pre>{{message}}</code> are available
for toasts. When an entity field **collides** with an envelope key (e.g. a record's own
`status`), reach it under the `data` key — <code v-pre>{{data.status}}</code>.

### A collection

```html
<tbody hx-get="/api/checklists" hx-trigger="load, refresh"
       handlebars-array-template="row-tpl"></tbody>

<template id="row-tpl">
  {{#each data}}
    <tr data-id="{{id}}"><td>{{name}}</td></tr>
  {{/each}}
</template>
```

The array template normalizes the response to `{ data: rows, meta }` — so the template
**must** iterate over `data` (an <code v-pre>{{#each data}}</code> block). It also
stashes `meta.table_sorts` for
[table sorting](./runtime) and can render pagination into a `data-pagination-target`.

> [!TIP] Template gotchas
> - An <code v-pre>{{#if}}</code> used as a bare HTML attribute inside a `<template>`
>   gets mangled — split the element into <code v-pre>{{#if}}</code> /
>   <code v-pre>{{else}}</code> branches between tags instead.
> - A self-loading element that should render a template (not swap raw HTML) needs
>   `hx-target="this"` + `hx-select="unset"` to override the body's `#app` targeting.

## Inline form errors

When a handler returns a `422` with a field-keyed `errors` map
([`validationError`](/backend/api-responses)), mark the form `data-form-errors="inline"`
and the runtime renders each message next to its field (adding `.field-error` /
`.field-error-text`, and a `.form-error-banner`); errors clear on focus. Without that
attribute, a business-rule error message ([`invariantError`](/backend/api-responses))
becomes a toast instead.

```html
<form hx-post="/api/checklists" hx-ext="json-enc" data-form-errors="inline">
```

## Success redirect / refresh

After a successful mutation, common follow-ups — all attribute-driven:

- **Redirect**: `data-on-success="redirect"` (uses `meta.redirect` or `data-redirect-url`).
- **Refresh a list**: give the list `hx-trigger="load, refresh"` and, on the form,
  `data-refresh-target="#the-list"` — the runtime fires `refresh` on it after a 2xx.
- **Fire an event**: `data-refresh-event="checklist:changed"` dispatches a
  `CustomEvent` on `document.body` that other elements can listen for.

Inside a modal, a successful submit auto-closes the modal and honors these same refresh
hooks — see [Modals & Toasts](./ui).

## Common `hx-*` recipes

```html
<!-- Self-loading, auth-gated panel -->
<div hx-get="/api/widgets" hx-trigger="auth-load, refresh"
     hx-target="this" hx-select="unset" hx-swap="innerHTML"
     handlebars-array-template="widget-tpl" data-requires-auth></div>

<!-- Debounced search into a list -->
<input type="search" data-search-target="#results" data-search-endpoint="/api/widgets">
```

For a dynamic `[id]` page, set `hx-get` at runtime (from the route param), then
`window.htmx.process(el)` before triggering — see [Runtime](./runtime).

## See also

- [Declarative Behavior](./declarative) — the response verbs used throughout.
- [API Responses](/backend/api-responses) — the envelope these templates render.
- [Modals & Toasts](./ui) — modal forms and toasts.
