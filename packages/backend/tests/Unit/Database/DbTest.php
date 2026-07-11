<?php

declare(strict_types=1);

use Adbar\Dot;
use Handlr\Config\Config;
use Handlr\Database\Db;
use Handlr\Database\DatabaseException;
use Ramsey\Uuid\Uuid;

function makeConfig(array $database): Config
{
    return new Config(new Dot(['database' => $database]));
}

// --- Config validation (eager, no connection) ----------------------------

it('throws when the DSN is missing or empty', function () {
    $cfg = makeConfig(['dsn' => '', 'user' => 'u', 'password' => 'p']);
    expect(fn () => new Db($cfg))->toThrow(DatabaseException::class);
});

it('throws when a MySQL DSN omits dbname', function () {
    $cfg = makeConfig([
        'dsn' => 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
        'user' => 'u',
        'password' => 'p',
    ]);
    expect(fn () => new Db($cfg))->toThrow(DatabaseException::class);
});

it('accepts a well-formed MySQL DSN and exposes the database name', function () {
    $cfg = makeConfig([
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=handlr_test;charset=utf8mb4',
        'user' => 'u',
        'password' => 'p',
    ]);

    $db = new Db($cfg);
    expect($db->getDatabaseName())->toBe('handlr_test');
});

it('does not eagerly connect (construction succeeds without a server)', function () {
    $cfg = makeConfig([
        'dsn' => 'mysql:host=203.0.113.1;port=3306;dbname=nope;charset=utf8mb4',
        'user' => 'u',
        'password' => 'p',
    ]);

    // No exception: PDO connection is deferred to first query.
    expect(new Db($cfg))->toBeInstanceOf(Db::class);
});

it('reports no active transaction before any connection', function () {
    $cfg = makeConfig([
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=handlr_test',
        'user' => 'u',
        'password' => 'p',
    ]);

    expect((new Db($cfg))->inTransaction())->toBeFalse();
});

// --- UUID <-> binary conversion (pure) -----------------------------------

it('uuidToBin returns a 16-byte string and round-trips via binToUuid', function () {
    $cfg = makeConfig([
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=handlr_test',
        'user' => 'u',
        'password' => 'p',
    ]);
    $db = new Db($cfg);

    $uuid = Uuid::uuid7()->toString();
    $bin = $db->uuidToBin($uuid);

    expect(strlen($bin))->toBe(16);
    expect($db->binToUuid($bin))->toBe($uuid);
});

it('uuidToBin passes through a value that is not a valid UUID', function () {
    $cfg = makeConfig([
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=handlr_test',
        'user' => 'u',
        'password' => 'p',
    ]);
    $db = new Db($cfg);

    // Already-binary (or otherwise non-UUID) input is returned unchanged.
    expect($db->uuidToBin('already-binary'))->toBe('already-binary');
});

it('binToUuid returns an already-formatted UUID unchanged', function () {
    $cfg = makeConfig([
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=handlr_test',
        'user' => 'u',
        'password' => 'p',
    ]);
    $db = new Db($cfg);

    $uuid = '00000000-0000-7000-8000-000000000000';
    expect($db->binToUuid($uuid))->toBe($uuid);
});
