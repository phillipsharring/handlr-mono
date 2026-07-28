<?php

declare(strict_types=1);

namespace Handlr\Database;

/**
 * One recorded write against a table row: what happened, to which row, and the
 * row's state before and after.
 *
 * This is the raw material an undo or audit consumer works from:
 * - **Insert** → `before` is null, `after` is the inserted row. To undo: delete `id`.
 * - **Update** → `before` is the prior row, `after` is the new row. To undo: write
 *   `before` back (and `after` is the optimistic guard: only revert if the row still
 *   matches it).
 * - **Delete** → `before` is the full row, `after` is null. To undo: re-insert `before`.
 *
 * The framework only records these; it does not compute or apply inverses. That is a
 * consumer's job (the undo module), which is why the shape carries everything an
 * inverse needs and nothing about how to apply it.
 */
final class Change
{
    /**
     * @param array<string,mixed>|null $before Row state before the write (null for an insert).
     * @param array<string,mixed>|null $after  Row state after the write (null for a delete).
     */
    public function __construct(
        public readonly ChangeOp $op,
        public readonly string $table,
        public readonly int|string $id,
        public readonly ?array $before,
        public readonly ?array $after,
    ) {}
}
