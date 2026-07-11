<?php

declare(strict_types=1);

use Handlr\Database\Query;
use Handlr\Database\DbInterface;

/**
 * Concrete Query that exposes the protected helpers for testing.
 */
class ExposedQuery extends Query
{
    public function pubRows(string $sql, array $p = []): array { return $this->rows($sql, $p); }
    public function pubRow(string $sql, array $p = []): ?array { return $this->row($sql, $p); }
    public function pubScalar(string $sql, array $p = []): mixed { return $this->scalar($sql, $p); }
    public function pubCount(string $sql, array $p = []): int { return $this->count($sql, $p); }
    public function pubColumn(string $sql, array $p = []): array { return $this->column($sql, $p); }
    public function pubUuidToBin(string $u): string { return $this->uuidToBin($u); }
    public function pubBinToUuid(string $b): string { return $this->binToUuid($b); }
}

it('rows() fetches all associative rows', function () {
    $db = $this->createMock(DbInterface::class);
    $stmt = $this->createMock(PDOStatement::class);

    $db->expects($this->once())
        ->method('execute')
        ->with('SELECT * FROM t', ['a'])
        ->willReturn($stmt);
    $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([['id' => 1], ['id' => 2]]);

    $q = new ExposedQuery($db);
    expect($q->pubRows('SELECT * FROM t', ['a']))->toBe([['id' => 1], ['id' => 2]]);
});

it('row() returns the first row', function () {
    $db = $this->createMock(DbInterface::class);
    $stmt = $this->createMock(PDOStatement::class);

    $db->method('execute')->willReturn($stmt);
    $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(['id' => 7]);

    $q = new ExposedQuery($db);
    expect($q->pubRow('SELECT ...'))->toBe(['id' => 7]);
});

it('row() returns null when there is no row', function () {
    $db = $this->createMock(DbInterface::class);
    $stmt = $this->createMock(PDOStatement::class);

    $db->method('execute')->willReturn($stmt);
    $stmt->method('fetch')->willReturn(false);

    $q = new ExposedQuery($db);
    expect($q->pubRow('SELECT ...'))->toBeNull();
});

it('scalar() returns a single value via fetchColumn', function () {
    $db = $this->createMock(DbInterface::class);
    $stmt = $this->createMock(PDOStatement::class);

    $db->method('execute')->willReturn($stmt);
    $stmt->method('fetchColumn')->willReturn('hello');

    $q = new ExposedQuery($db);
    expect($q->pubScalar('SELECT ...'))->toBe('hello');
});

it('count() casts the scalar result to int', function () {
    $db = $this->createMock(DbInterface::class);
    $stmt = $this->createMock(PDOStatement::class);

    $db->method('execute')->willReturn($stmt);
    $stmt->method('fetchColumn')->willReturn('42'); // DB returns string

    $q = new ExposedQuery($db);
    expect($q->pubCount('SELECT COUNT(*) ...'))->toBe(42);
});

it('column() returns a flat array via FETCH_COLUMN', function () {
    $db = $this->createMock(DbInterface::class);
    $stmt = $this->createMock(PDOStatement::class);

    $db->method('execute')->willReturn($stmt);
    $stmt->method('fetchAll')->with(PDO::FETCH_COLUMN)->willReturn(['a', 'b', 'c']);

    $q = new ExposedQuery($db);
    expect($q->pubColumn('SELECT name ...'))->toBe(['a', 'b', 'c']);
});

it('delegates uuid conversion helpers to the Db', function () {
    $db = $this->createMock(DbInterface::class);
    $db->method('uuidToBin')->with('U')->willReturn('BIN');
    $db->method('binToUuid')->with('BIN')->willReturn('U');

    $q = new ExposedQuery($db);
    expect($q->pubUuidToBin('U'))->toBe('BIN');
    expect($q->pubBinToUuid('BIN'))->toBe('U');
});
