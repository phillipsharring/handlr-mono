<?php

declare(strict_types=1);

namespace Handlr\Policies;

use RuntimeException;

/**
 * Thrown by {@see Decision::orDeny()} when a policy denies an action.
 *
 * Carries the HTTP status to surface (403 by default). Map it to a response in
 * an error pipe.
 */
class PolicyDenied extends RuntimeException
{
    public function __construct(string $message = 'Denied.', private readonly int $status = 403)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
