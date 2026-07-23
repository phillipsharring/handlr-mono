<?php

declare(strict_types=1);

namespace Handlr\Invariants;

/**
 * A single rule that must hold for a request to proceed.
 *
 * One invariant is one rule — build a class per rule, not a bag of `can*`
 * methods. The rule's subject and any dependencies are supplied via the
 * constructor; `check()` reports whether it holds.
 *
 * Distinguish these *request* invariants (quotas, state preconditions — "can
 * this happen right now?") from *domain* invariants ("this aggregate can never
 * be in an illegal state"), which belong inside the Record/domain itself, not
 * here.
 *
 * ```php
 * final class ChecklistUnderItemLimit implements Invariant
 * {
 *     public function __construct(
 *         private ChecklistRecord $checklist,
 *         private int $limit,
 *     ) {}
 *
 *     public function check(): ?Violation
 *     {
 *         return $this->checklist->itemCount() <= $this->limit
 *             ? null
 *             : new Violation("This list is at its {$this->limit}-item limit.", 'item_limit');
 *     }
 * }
 * ```
 */
interface Invariant
{
    /**
     * @return Violation|null Null when the invariant holds; a Violation otherwise.
     */
    public function check(): ?Violation;
}
