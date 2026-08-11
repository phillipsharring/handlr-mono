# ADR 0005 — Explicit session-id transport (extract / emit) over PHP sessions

- **Status:** Proposed 2026-08-09
- **Date:** 2026-08-09
- **Deciders:** Phillip Harrington (with Claude)
- **Relates to:** `0004-policy-invariant-resolution-layer.md` (the authz layer this
  feeds — `AuthContext` is populated the same way regardless of transport).
  Supersedes the native-auth mechanism proposed in **reuselists**
  `docs/adr/0003-native-ios-client.md` (opaque bearer tokens + a tokens table);
  see _Rejected alternatives_.

---

## Context

Handlr authenticates a request in one indivisible lump. `StartSessionPipe` calls
`session_start()`, and PHP silently does three things:

1. **X → token** — reads the session id from the `PHPSESSID` cookie.
2. **token → identity** — loads the `$_SESSION` row; `SessionAuthPipe` copies
   `user_id` into `AuthContext`.
3. **token → X** — emits `Set-Cookie` on the response. The app never touches it.

Everything downstream (`RequireAuthPipe`, the policy/invariant layer from ADR 0004,
handlers) reads only `AuthContext`. Confirmed against reuselists: the sole
app-level payload in `$_SESSION` is `user_id`. The session is **upstream** of
`AuthContext`, not underneath it — one pipe is the only wire between them.

Two facts create the pressure:

- **A native client (iOS) has no cookie jar.** It carries its credential in a
  header (`Authorization: Bearer …` or `X-TOKEN`). Steps 1 and 3 above are
  cookie-shaped and invisible, so there is no seam to swap.
- **The credential the browser already uses — the PHP session id — is itself an
  opaque, high-entropy, server-revocable token.** A native client could carry
  *that*. Nothing about the session engine is browser-specific; only its
  transport is.

### What we explicitly are **not** doing, and why

An earlier direction (reuselists ADR 0003) proposed a **second identity backend**:
an opaque-token table, a `BearerAuthPipe`, and an `api.mobile` junction. Examined
against the actual need, that duplicates machinery we already have:

- A tokens table is `id → user_id + expiry + server-side data`. **That is the
  sessions table.** Two tables, one job.
- Session ids already revoke (`session_destroy()` deletes the row) and already
  support multiple concurrent logins per user (one row per device — nothing
  requires one-session-per-user). The advantages once claimed for opaque tokens
  over sessions assumed a strawman single-session model.
- `api.mobile` forks routing on **who is asking** (client), when junctions
  elsewhere fork on **what is required** (context). A route should not care
  whether a phone or a browser called it — only whether the caller is authed.

The real difference between the browser and the native client is **not the
identity backend. It is the transport of the session id — in and out.** That is
the only thing to abstract.

## Decision

### 1. Keep PHP sessions as the one engine

Server-side session handling stays exactly as-is: `DatabaseSessionDriver`,
serialization, storage, gc — all reused. `SessionAuthPipe` remains the single
populator of `AuthContext`. No second backend, no tokens table.

### 2. A symmetric `SessionTransport` seam

Introduce one interface with two ends — replacing PHP's cookie magic on **both**
sides:

```php
interface SessionTransport
{
    /** X → token: pull the session id from the request, or null if absent. */
    public function extract(Request $request): ?string;

    /** token → X: put the session id back on the response, in this modality. */
    public function emit(Response $response, string $sessionId): Response;
}
```

> **Phasing.** Phase 1 (this scaffold) ships `extract`/`emit` only, bound to
> `CookieTransport` by default — behavior-identical to today. **CSRF is left exactly
> as it is, session-bound.** The `isAmbient()` method and the CSRF-gating rule in §4
> are **Phase 2**, pulled in only when a real `HeaderTransport` binding goes live for a
> native client — at which point CSRF-vs-transport is decoupled the same way (see §4,
> retained as the design of record for that step).

Implementations:

- **`CookieTransport`** — the default. `extract()` reads `PHPSESSID`; `emit()` is
  the `Set-Cookie` (HttpOnly). This is today's behavior made *explicit* rather
  than delegated to PHP's automatic cookie handling. `isAmbient() === true`.
- **`HeaderTransport`** — for native / non-browser clients. `extract()` reads a
  configured header (`Authorization: Bearer <id>` or `X-TOKEN`); `emit()` writes
  the id into a response header (and, at login only, the body). `isAmbient() === false`.

Transports are **chainable**: a `ChainSessionTransport` tries carriers in order;
the carrier that matched inbound owns the response outbound (so a cookie request
gets a cookie back, a header request gets a header back). One server serves both
client kinds without forking routes.

### 3. Wiring — the id round-trips, explicitly

`StartSessionPipe` gains the transport:

- **In:** `id = transport->extract($request)`; `session->start($id)`
  (`session_id($id)` before `session_start()`; null → PHP mints a fresh id, as
  today).
- **Out:** on the way back, `transport->emit($response, session->id())` so a
  freshly minted id reaches a cookieless client. `Session` grows an `id(): string`
  getter for this.

For the header path, PHP's own cookie emission is disabled
(`session.use_cookies = 0`) so `emit()` is the sole exit — no double-send.

**One junction, client-agnostic.** No `api.mobile`. The authed junction is
unchanged; only the bound `SessionTransport` differs (a chain of header + cookie).

### 4. CSRF keys off *ambient*, per request _(Phase 2 — deferred)_

CSRF exploits ambient credentials — the browser auto-replays the cookie on a
forged cross-origin request. A header credential is not ambient (nothing
auto-attaches it; cross-origin JS cannot read a native app's Keychain), so the
attack's mechanism is absent — not "defense skipped," but "no attack surface."

The rule, enforced on the **matched transport of the current request**, never a
route flag:

> **CSRF is enforced iff the request authed via an ambient (cookie) transport.**

A cookie present and used → CSRF enforced, full stop. A request cannot declare
itself header-auth to dodge CSRF while riding a cookie. The CSRF pipe reads
`transport->isAmbient()` for the carrier that actually matched.

## Rejected alternatives

- **Opaque bearer tokens + tokens table + `BearerAuthPipe` + `api.mobile`**
  (reuselists ADR 0003). A second backend duplicating the sessions table; routing
  forked on client identity. Superseded by this ADR for the native-auth question.
- **`SessionIdResolver` (extract-only)** — a prior spike (reverted). It abstracted
  the inbound half but left the outbound `Set-Cookie` as PHP magic, so a cookieless
  client had no way to receive a freshly minted id. Incomplete; folded into the
  symmetric `SessionTransport` here.
- **Two tolerant auth pipes** (`SessionAuth` + `BearerAuth` both in one stack) —
  still implies two identity backends. Unnecessary once the credential is a single
  session id carried by a swappable transport.

## Consequences

**Positive**

- One backend, one populator, one junction. Less code than a second-backend design;
  no duplicate table.
- The cookie↔session translation becomes explicit and testable on both ends; a
  fake transport swaps in for tests with no cookie jar.
- Native support is a transport binding, not a parallel auth system. iOS reuses
  every pipe, policy, and handler unchanged (per ADR 0004).

**Negative / to handle**

- `session.use_cookies = 0` required on the header path so `emit()` owns the exit.
- CSRF enforcement must be conditional on `isAmbient()` — a security-critical
  branch, tested directly.
- The header path returns the session id in the login response body; the cookie
  path never does (browser id stays HttpOnly, never echoed).

**Out of scope — explicitly not decided here**

- **Session locking & gc/expiry.** Pure PHP-session properties, unchanged by a
  transport-only change; the browser already lives with them. They surface only as
  *iOS operational tuning* (heavy request parallelism → `session_write_close()`
  early; months-long login → raise `gc_maxlifetime`) if a native usage pattern
  exposes them. Revisit then, not now.
- Retiring PHP sessions entirely (a different, larger project). This ADR keeps the
  engine; it only makes its transport explicit and swappable.

## Scope

Framework: `packages/backend` — `Handlr\Session\SessionTransport` (+ `Cookie`,
`Header`, `Chain` implementations), `StartSessionPipe`, the CSRF pipe's ambient
check, and `Session::id()`. No app-level changes required for existing browser
apps: the default `CookieTransport` reproduces current behavior.
