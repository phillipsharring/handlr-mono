<?php

declare(strict_types=1);

use Handlr\Invariants\Invariant;
use Handlr\Invariants\Violation;

// ── Fixture: one rule, one class ──

class CountUnderLimit implements Invariant
{
    public function __construct(private int $count, private int $limit) {}

    public function check(): ?Violation
    {
        return $this->count <= $this->limit
            ? null
            : new Violation("Over the {$this->limit} limit.", 'limit');
    }
}

// ── Tests ──

it('returns null when the invariant holds', function () {
    expect((new CountUnderLimit(3, 10))->check())->toBeNull();
});

it('returns a Violation carrying message and code when broken', function () {
    $violation = (new CountUnderLimit(11, 10))->check();

    expect($violation)->toBeInstanceOf(Violation::class)
        ->and($violation->message)->toBe('Over the 10 limit.')
        ->and($violation->code)->toBe('limit');
});
