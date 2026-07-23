<?php

declare(strict_types=1);

namespace Handlr\Resolution;

use Handlr\Core\RequestException;
use Handlr\Core\Response;
use Throwable;

/**
 * Thrown when a Resolver cannot locate a record for a given id.
 *
 * A route-bound `{id}` that matches nothing is always a 404 — there is no
 * "optional" case — so resolution fails loudly. Extends RequestException so the
 * global ErrorPipe renders it as a 404 without extra wiring.
 */
class RecordNotFound extends RequestException
{
    public function __construct(
        string $message = 'Not found.',
        int $statusCode = Response::HTTP_NOT_FOUND,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
