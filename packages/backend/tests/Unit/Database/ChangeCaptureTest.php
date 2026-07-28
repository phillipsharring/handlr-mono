<?php

declare(strict_types=1);

use Handlr\Database\Change;
use Handlr\Database\ChangeOp;
use Handlr\Database\ChangeRecorder;
use Handlr\Database\DbInterface;
use Handlr\Database\NullRecorder;
use Handlr\Database\Record;
use Handlr\Database\Table;

// ── Fixtures ──

class CapRecord extends Record
{
    protected bool $useUuid = true;
}

/** @extends Table<CapRecord> */
class CapTable extends Table
{
    protected string $tableName = 'caps';
    protected string $recordClass = CapRecord::class;
}

class SpyRecorder implements ChangeRecorder
{
    /** @var Change[] */
    public array $changes = [];

    public function __construct(private bool $recording = true) {}

    public function isRecording(): bool
    {
        return $this->recording;
    }

    public function record(Change $change): void
    {
        $this->changes[] = $change;
    }
}

/** A no-op database: writes go nowhere, so we can test the capture wiring in isolation. */
class FakeDb implements DbInterface
{
    public function getDatabaseName(): string
    {
        return 'fake';
    }

    public function execute(string $sql, array $params = []): false|PDOStatement
    {
        return false;
    }

    public function insertId(): int
    {
        return 1;
    }

    public function affectedRows(?PDOStatement $stmt = null): int
    {
        return 1;
    }

    public function uuidToBin(string $uuid): string
    {
        return $uuid;
    }

    public function binToUuid(string $bin): string
    {
        return $bin;
    }

    public function beginTransaction(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollBack(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return false;
    }
}

// ── Tests ──

it('records an insert as a Change when the recorder is on', function () {
    $spy = new SpyRecorder(recording: true);
    $table = new CapTable(new FakeDb(), $spy);

    $record = new CapRecord(['name' => 'alpha']);
    $table->insert($record);

    expect($spy->changes)->toHaveCount(1);

    $change = $spy->changes[0];
    expect($change->op)->toBe(ChangeOp::Insert)
        ->and($change->table)->toBe('caps')
        ->and($change->id)->toBe($record->id)
        ->and($change->before)->toBeNull()
        ->and($change->after['name'])->toBe('alpha');
});

it('records nothing when the recorder is off', function () {
    $spy = new SpyRecorder(recording: false);
    $table = new CapTable(new FakeDb(), $spy);

    $table->insert(new CapRecord(['name' => 'beta']));

    expect($spy->changes)->toHaveCount(0);
});

it('defaults to a no-op NullRecorder when none is injected', function () {
    // No recorder passed → constructor default NullRecorder → no error, no capture.
    $table = new CapTable(new FakeDb());
    $record = $table->insert(new CapRecord(['name' => 'gamma']));

    expect($record->name)->toBe('gamma');
});

it('cascades() defaults to none', function () {
    expect((new CapTable(new FakeDb()))->cascades())->toBe([]);
});

it('NullRecorder never records and its record() is a no-op', function () {
    $recorder = new NullRecorder();

    expect($recorder->isRecording())->toBeFalse();
    $recorder->record(new Change(ChangeOp::Insert, 'caps', '1', null, ['name' => 'x']));

    expect($recorder->isRecording())->toBeFalse();
});
