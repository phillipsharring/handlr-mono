<?php

declare(strict_types=1);

namespace Handlr\Session;

use Handlr\Core\Request;
use Handlr\Core\Response;

/**
 * Default transport: defer to PHP's native cookie handling on both ends.
 *
 * This is today's behavior made explicit-by-binding without changing it.
 * `extract()` returns null so `session_start()` reads the `PHPSESSID` cookie
 * itself (and mints + `Set-Cookie`s a fresh id on first contact), and `emit()`
 * is a pass-through because PHP has already written the cookie. The transport
 * is intentionally transparent here — a browser app behaves byte-for-byte as
 * before, and CSRF stays session-bound and untouched.
 *
 * A fuller version could read `PHPSESSID` and write `Set-Cookie` by hand, but
 * that would have to reproduce every cookie flag PHP manages (Secure, HttpOnly,
 * SameSite, path, lifetime). Deferring to PHP is the safe default; the explicit
 * seam that matters for non-cookie clients lives in {@see HeaderTransport}.
 */
final class CookieTransport implements SessionTransport
{
    public function extract(Request $request): ?string
    {
        // null → session_start() reads the cookie natively, as it always has.
        return null;
    }

    public function emit(Response $response, string $sessionId): Response
    {
        // PHP already emitted Set-Cookie during session_start(); nothing to add.
        return $response;
    }
}
