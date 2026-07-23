<?php

declare(strict_types=1);

use Handlr\Database\Record;
use Handlr\Database\Table;
use Handlr\Resolution\RecordNotFound;
use Handlr\Resolution\TableResolver;

// ── Fixtures ──

class ResolverFakeRecord extends Record {}

/**
 * @extends Table<ResolverFakeRecord>
 */
class ResolverFakeTable extends Table
{
    protected string $tableName = 'fakes';
    protected string $recordClass = ResolverFakeRecord::class;

    public ?Record $stub = null;

    // Skip the parent constructor's DbInterface requirement — findById is stubbed.
    public function __construct() {}

    public function findById(int|string $id): ?Record
    {
        return $this->stub;
    }
}

// ── Tests ──

it('returns the located record on a hit', function () {
    $table = new ResolverFakeTable();
    $table->stub = new ResolverFakeRecord(['id' => 'abc']);

    expect((new TableResolver())->resolve($table, 'abc'))->toBe($table->stub);
});

it('throws RecordNotFound on a miss', function () {
    $table = new ResolverFakeTable();
    $table->stub = null;

    expect(fn() => (new TableResolver())->resolve($table, 'nope'))
        ->toThrow(RecordNotFound::class);
});
