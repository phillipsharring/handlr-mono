<?php

declare(strict_types=1);

namespace Handlr\Invariants;

/**
 * The result of a broken Invariant: why it failed, and an optional machine code.
 *
 * A satisfied invariant returns `null`, not a Violation — the common "fine" case
 * is silence. A Violation is the complaint, carrying enough to tell the user.
 */
final class Violation
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $code = null,
    ) {}
}
