<?php

declare(strict_types=1);

namespace Handlr\Session;

use Handlr\Core\Request;
use Handlr\Core\Response;

/**
 * Carry the session id in a request/response header — the door for native /
 * non-browser clients (iOS, CLI) that have no cookie jar.
 *
 * `extract()` reads the id from a configured header; `emit()` writes the
 * current id back on the same header so a client can capture a freshly minted
 * id (e.g. from the login response) and echo it thereafter.
 *
 * Two common shapes:
 *
 * - a dedicated header, `X-Session-Id: <id>` (the default), or
 * - a bearer token, `Authorization: Bearer <id>` — construct with
 *   `header: 'Authorization', bearer: true`.
 *
 * The inbound id is validated ({@see ID_PATTERN}) before it is trusted; a junk
 * or hostile value resolves to null (PHP then mints a fresh id), so a bad
 * header can never fixate a session.
 *
 * ## Operational notes (see ADR 0005)
 *
 * - Wire `session.use_cookies = 0` when this transport is active so PHP does not
 *   also emit `Set-Cookie` — `emit()` becomes the sole exit.
 * - CSRF gating for this (non-ambient) transport is a Phase 2 concern; not
 *   handled here yet.
 */
final class HeaderTransport implements SessionTransport
{
    /**
     * PHP session ids are drawn from `[A-Za-z0-9,-]` (the widest charset PHP
     * emits across `session.sid_bits_per_character` settings) and are short.
     * Reject anything outside that shape before trusting a client-supplied id.
     */
    public const ID_PATTERN = '/^[A-Za-z0-9,\-]{1,128}$/';

    public function __construct(
        private readonly string $header = 'X-Session-Id',
        private readonly bool $bearer = false,
    ) {}

    public function extract(Request $request): ?string
    {
        $value = $request->getHeader($this->header);

        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($this->bearer) {
            if (stripos($value, 'Bearer ') !== 0) {
                return null;
            }
            $value = trim(substr($value, 7));
        }

        return preg_match(self::ID_PATTERN, $value) === 1 ? $value : null;
    }

    public function emit(Response $response, string $sessionId): Response
    {
        if ($sessionId === '') {
            return $response;
        }

        // Echo the id back on the plain header name (without the "Bearer "
        // scheme prefix — the client re-attaches that on the way in).
        return $response->withHeader($this->header, $sessionId);
    }
}
