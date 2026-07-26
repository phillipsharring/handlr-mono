# CSRF

Handlr uses a **token-in-header** CSRF strategy that works cleanly with both `fetch`
and HTMX, and pairs with a same-origin check. The backend owns the token; the frontend
mirrors it from a cookie into a request header.

## The strategy

1. The backend keeps a CSRF token in the session and mirrors it into a
   JS-readable cookie named **`XSRF-TOKEN`** (`httponly: false`, `SameSite=Lax`,
   `secure` on HTTPS), and also returns it in the **`X-CSRF-Token`** response header.
2. The frontend reads the cookie and sends the token back as the **`X-CSRF-Token`**
   request header on every mutating request.
3. The backend validates the header against the session token (constant-time), then
   **rotates** the token after a successful mutation.

Because the token lives in a cookie, all tabs share it, and a page reload doesn't lose
it.

## Frontend wiring

`./init` turns this on — you don't call anything. Under the hood (`core/csrf.js`):

- `window.fetch` is patched to inject `X-CSRF-Token` on `/api/` requests.
- An `htmx:configRequest` listener injects the same header on every HTMX request.

If you make your own requests, use `apiFetch` (which sets the header, JSON-encodes, and
captures a rotated token) or read the token yourself:

```js
import { getCsrfToken } from '@phillipsharring/handlr-frontend';
fetch('/api/thing', { method: 'POST', headers: { 'X-CSRF-Token': getCsrfToken() } });
```

`setCsrfToken()` exists for API symmetry but is a **no-op** — the cookie is the source
of truth, managed by the backend.

## Backend pipes

Three pipes cooperate, wired into the same [junctions](/backend/routing#junctions) as
the session/auth pipes:

- **`VerifyOriginPipe`** — for non-safe methods, checks the `Origin`/`Referer` host
  matches `Host`; a mismatch is a `403`. Absent headers (a bare `curl`) are allowed —
  the token is the primary defense, origin is defense-in-depth.
- **`EnsureCsrfTokenPipe`** — ensures a session token exists, sets the `XSRF-TOKEN`
  cookie and the `X-CSRF-Token` response header.
- **`VerifyCsrfTokenPipe`** — for non-safe methods, validates the incoming
  `X-CSRF-Token` header (`403` on failure), then **rotates** the token after the
  request succeeds.

```php
$router->group('/api', [CorsPipe::class, VerifyOriginPipe::class])
    ->through([StartSessionPipe::class, SessionAuthPipe::class, EnsureCsrfTokenPipe::class])
        ->through([VerifyCsrfTokenPipe::class, RequireAuthPipe::class])
            ->junction('api.authed')
        ->end()
    ->end()
->end();
```

Safe methods (GET/HEAD) aren't verified — they don't mutate — but `EnsureCsrfTokenPipe`
still hands out a token so the first mutation has one.

## Token rotation and 403 recovery

Because the token rotates after each successful mutation, a stale in-flight token can
occasionally produce a `403`. The frontend handles this: on a `403` the auth-state
layer captures the fresh token (already in the rotated cookie/header) and shows a
"session expired" warning toast, so the user can retry with the current token rather
than hitting a dead end. See [Auth State](./auth-state).

## See also

- [Auth State](./auth-state) — 401/403 interception on the client.
- [Auth](/backend/auth) — the session pipes CSRF runs beside.
