<?php

declare(strict_types=1);

use Handlr\Handlers\HandlerResult;

/**
 * HandlerResult is used as an injected instance (never statically).
 * These tests new up a prototype and call ok()/fail() on it, mirroring
 * how handlers receive `private HandlerResult $result`.
 */

it('ok() builds a success result with data and empty errors', function () {
    $r = (new HandlerResult())->ok(['user' => ['id' => 1]]);

    expect($r->success)->toBeTrue();
    expect($r->data)->toBe(['user' => ['id' => 1]]);
    expect($r->errors)->toBe([]);
    expect($r->meta)->toBe([]);
});

it('ok() carries optional metadata', function () {
    $r = (new HandlerResult())->ok(['x' => 1], ['page' => 1, 'total' => 50]);

    expect($r->meta)->toBe(['page' => 1, 'total' => 50]);
});

it('ok() with no args is a bare success', function () {
    $r = (new HandlerResult())->ok();

    expect($r->success)->toBeTrue();
    expect($r->data)->toBeNull();
});

it('fail() builds a failure with errors and null data', function () {
    $r = (new HandlerResult())->fail(['email' => 'taken']);

    expect($r->success)->toBeFalse();
    expect($r->data)->toBeNull();
    expect($r->errors)->toBe(['email' => 'taken']);
});

it('fail() carries optional metadata (error codes etc.)', function () {
    $r = (new HandlerResult())->fail(['Payment failed'], ['code' => 'CARD_DECLINED']);

    expect($r->errors)->toBe(['Payment failed']);
    expect($r->meta)->toBe(['code' => 'CARD_DECLINED']);
});

it('ok()/fail() produce a fresh instance, not a mutated self', function () {
    $proto = new HandlerResult();
    $ok = $proto->ok(['a' => 1]);

    expect($ok)->not->toBe($proto);
    expect($proto->success)->toBeNull(); // prototype untouched
});

it('constructor defaults to indeterminate success (null)', function () {
    $r = new HandlerResult();

    expect($r->success)->toBeNull();
    expect($r->data)->toBeNull();
    expect($r->errors)->toBe([]);
    expect($r->meta)->toBe([]);
});

it('properties are readonly value-object semantics', function () {
    $r = (new HandlerResult())->ok(['a' => 1]);

    expect(fn () => $r->success = false)->toThrow(Error::class);
});
