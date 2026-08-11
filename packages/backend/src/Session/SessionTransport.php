<?php

declare(strict_types=1);

namespace Handlr\Session;

use Handlr\Core\Request;
use Handlr\Core\Response;

/**
 * Carries a session id in and out of a request — decoupled from the session
 * engine itself.
 *
 * PHP normally does this invisibly: it reads the id from the `PHPSESSID`
 * cookie on the way in and writes `Set-Cookie` on the way out. That fuses two
 * transport concerns into `session_start()` and leaves no seam for a client
 * that doesn't use cookies (a native/iOS app carrying the id in a header).
 *
 * A `SessionTransport` makes both halves explicit and swappable while the
 * server-side session engine (PHP sessions, {@see DatabaseSessionDriver},
 * serialization, gc) stays exactly as-is:
 *
 * - {@see extract()} — X → token: pull the session id off the request.
 * - {@see emit()}    — token → X: put the (possibly freshly minted) id back on
 *   the response, in the same modality it arrived.
 *
 * The bound transport is the only thing that differs between a browser client
 * ({@see CookieTransport}, the default) and a native client
 * ({@see HeaderTransport}). Everything downstream — `SessionAuthPipe`,
 * `AuthContext`, policies, handlers — is identical regardless.
 *
 * See ADR 0005 (`docs/adr/0005-explicit-session-id-transport.md`). CSRF gating
 * per transport is a Phase 2 concern and deliberately absent from this
 * interface for now.
 */
interface SessionTransport
{
    /**
     * Pull the session id out of the request.
     *
     * @return string|null The session id to adopt, or null to let PHP fall back
     *                     to its default (read the cookie, or mint a fresh id).
     */
    public function extract(Request $request): ?string;

    /**
     * Put the session id onto the response, in this transport's modality.
     *
     * Called on the way out so a freshly minted id can reach a cookieless
     * client. Returns the (possibly new) response.
     *
     * @param string $sessionId The current session id (from `Session::id()`).
     */
    public function emit(Response $response, string $sessionId): Response;
}
