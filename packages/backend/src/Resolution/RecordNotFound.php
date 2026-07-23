<?php

declare(strict_types=1);

namespace Handlr\Resolution;

use RuntimeException;

/**
 * Thrown when a Resolver cannot locate a record for a given id.
 *
 * A route-bound `{id}` that matches nothing is always a 404 — there is no
 * "optional" case — so resolution fails loudly. Map this to a 404 response in
 * an error pipe.
 */
class RecordNotFound extends RuntimeException {}
