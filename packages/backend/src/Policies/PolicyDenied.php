<?php

declare(strict_types=1);

namespace Handlr\Policies;

use Handlr\Core\RequestException;
use Handlr\Core\Response;
use Throwable;

/**
 * Thrown by {@see Decision::orDeny()} when a policy denies an action.
 *
 * Extends RequestException (default 403) so the global ErrorPipe renders it
 * wherever a policy is consulted — inside a resolve pipe or directly in a
 * handler.
 */
class PolicyDenied extends RequestException
{
    public function __construct(
        string $message = 'Denied.',
        int $statusCode = Response::HTTP_FORBIDDEN,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
