<?php

declare(strict_types=1);

namespace Handlr\Policies;

/**
 * The binary answer a Policy returns: granted, or denied (with an optional
 * reason). Build one with the named constructors {@see grant()} / {@see deny()}.
 */
final class Decision
{
    private function __construct(
        public readonly bool $granted,
        public readonly ?string $reason = null,
    ) {}

    public static function grant(): self
    {
        return new self(true);
    }

    public static function deny(?string $reason = null): self
    {
        return new self(false, $reason);
    }

    public function denied(): bool
    {
        return !$this->granted;
    }

    /**
     * Guard sugar for a pipe: return quietly when granted, throw otherwise.
     *
     * @param int $status HTTP status to surface on denial (default 403).
     *
     * @throws PolicyDenied When the decision is a denial.
     */
    public function orDeny(int $status = 403): void
    {
        if (!$this->granted) {
            throw new PolicyDenied($this->reason ?? 'Denied.', $status);
        }
    }
}
