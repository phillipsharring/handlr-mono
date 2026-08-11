<?php

declare(strict_types=1);

namespace Handlr\Session;

use Handlr\Core\Request;
use Handlr\Core\Response;

/**
 * Try several transports in order so one server can carry the session id for
 * mixed clients — put {@see HeaderTransport} first and fall through to
 * {@see CookieTransport} for browsers:
 *
 * ```php
 * new ChainSessionTransport(
 *     new HeaderTransport('Authorization', bearer: true),  // native clients
 *     new CookieTransport(),                               // browsers (terminator)
 * );
 * ```
 *
 * `extract()` returns the first non-null id and remembers which transport
 * matched. `emit()` then answers in kind — a cookie request gets a cookie back,
 * a header request gets a header back — so a browser response never leaks the id
 * in a readable header.
 *
 * ## Request-scoped state — assumption
 *
 * The matched transport is held on this instance between `extract()` and
 * `emit()`. That is safe under PHP-FPM (one request per process, no
 * interleaving) and because {@see StartSessionPipe} always calls `extract()`
 * before `emit()` within a single request. Under a shared-process async runtime
 * (Swoole, RoadRunner) this instance must NOT be a cross-request singleton.
 *
 * ## Why the fallback is the *last* transport
 *
 * `CookieTransport::extract()` returns null (it defers to PHP), so "a browser
 * matched via cookie" is indistinguishable from "no credential at all" — both
 * are null, and neither marks a match. If `emit()` fell back to the *first*
 * transport (the header one), a plain browser response would get the session id
 * written into a readable header, defeating the HttpOnly cookie. So the fallback
 * is the last transport — the cookie terminator, whose `emit()` is a no-op — and
 * a browser response never leaks the id.
 *
 * ## Phase 2 limitation
 *
 * The flip side: on a native client's very first login there is no inbound id,
 * nothing matches, and `emit()` falls back to the cookie terminator — so the
 * freshly minted id is NOT echoed on a header. Getting a first-contact id back
 * to a header client is a Phase 2 refinement (see ADR 0005); it does not bite
 * today because no `HeaderTransport` binding is live yet.
 */
final class ChainSessionTransport implements SessionTransport
{
    /** @var list<SessionTransport> */
    private readonly array $transports;

    private ?SessionTransport $matched = null;

    public function __construct(SessionTransport ...$transports)
    {
        $this->transports = array_values($transports);
    }

    public function extract(Request $request): ?string
    {
        $this->matched = null;

        foreach ($this->transports as $transport) {
            $id = $transport->extract($request);

            if ($id !== null) {
                $this->matched = $transport;

                return $id;
            }
        }

        return null;
    }

    public function emit(Response $response, string $sessionId): Response
    {
        // Answer in the modality that matched inbound. When nothing matched
        // (e.g. first contact), fall back to the LAST transport — the cookie
        // terminator whose emit is a no-op — so a browser response never leaks
        // the id on a header. See class note.
        $fallback = $this->transports === [] ? null : $this->transports[array_key_last($this->transports)];
        $transport = $this->matched ?? $fallback;

        return $transport?->emit($response, $sessionId) ?? $response;
    }
}
