<?php

declare(strict_types=1);

use Handlr\Validation\Validator;
use Handlr\Validation\Rules\RuleValidatorFactory;
use Handlr\Validation\Sanitizers\SanitizerFactory;
use Handlr\Validation\ValidationException;

/**
 * Integration tests over the real rule + sanitizer factories (no mocks).
 * Exercises the wiring in Validator: rule parsing, sanitization, nullable/
 * default handling, sanitizer substitution, and error collection.
 */
function makeValidator(): Validator
{
    return new Validator(new RuleValidatorFactory(), new SanitizerFactory());
}

it('passes valid data and exposes sanitized values', function () {
    $v = makeValidator();

    $ok = $v->validate(
        ['email' => '  a@b.com ', 'name' => '  Bob '],
        ['email' => ['required', 'email'], 'name' => ['required', 'string|trim']],
    );

    expect($ok)->toBeTrue();
    expect($v->isValid())->toBeTrue();
    expect($v->errors())->toBe([]);
    expect($v->sanitized('name'))->toBe('Bob');       // trimmed
    expect($v->sanitized('email'))->toBe('a@b.com');  // email sanitizer trims
});

it('collects a per-field error and stops that field on first failure', function () {
    $v = makeValidator();

    $ok = $v->validate(
        ['email' => 'nope'],
        ['email' => ['required', 'email']],
    );

    expect($ok)->toBeFalse();
    expect($v->errors())->toHaveKey('email');
});

it('parses rule arguments (min/max) from the rule string', function () {
    $v = makeValidator();

    $ok = $v->validate(
        ['name' => 'ab'],
        ['name' => ['string|min:3,max:100']],
    );

    expect($ok)->toBeFalse();
    expect($v->errors()['name'])->toContain('at least 3');
});

it('nullable empty field falls back to a type-cast default', function () {
    $v = makeValidator();

    $v->validate(
        ['count' => ''],
        ['count' => ['nullable', 'int', 'default|5']],
    );

    expect($v->isValid())->toBeTrue();
    expect($v->sanitized('count'))->toBe(5);   // cast to int
});

it('nullable empty field with no default sanitizes to null', function () {
    $v = makeValidator();

    $v->validate(
        ['bio' => null],
        ['bio' => ['nullable', 'string']],
    );

    expect($v->sanitized('bio'))->toBeNull();
});

it('nullable does NOT treat "0" / 0 as empty', function () {
    $v = makeValidator();

    $v->validate(
        ['count' => '0'],
        ['count' => ['nullable', 'int', 'default|9']],
    );

    // '0' is a real value, so the default is not applied
    expect($v->sanitized('count'))->toBe(0);
});

it('substitutes the string sanitizer for uuid rules', function () {
    $v = makeValidator();
    $uuid = '00000000-0000-4000-8000-000000000000';

    $ok = $v->validate(
        ['id' => $uuid],
        ['id' => ['required', 'uuid']],
    );

    expect($ok)->toBeTrue();
    expect($v->sanitized('id'))->toBe($uuid);
});

it('passes non-sanitized rule values through unchanged (e.g. in)', function () {
    $v = makeValidator();

    $v->validate(
        ['status' => 'active'],
        ['status' => ['required', 'in|active,pending']],
    );

    expect($v->sanitized('status'))->toBe('active');
});

it('records both fields for a failing confirmed rule', function () {
    $v = makeValidator();

    $ok = $v->validate(
        ['password' => 'secret', 'password_confirmation' => 'different'],
        ['password' => ['confirmed']],
    );

    expect($ok)->toBeFalse();
    expect($v->errors())->toHaveKey('password_confirmation');
});

it('throws for an unknown rule name', function () {
    $v = makeValidator();

    expect(fn () => $v->validate(['x' => 1], ['x' => ['bogusrule']]))
        ->toThrow(ValidationException::class);
});

it('validates multiple fields independently', function () {
    $v = makeValidator();

    $ok = $v->validate(
        ['email' => 'bad', 'age' => 'x'],
        ['email' => ['email'], 'age' => ['int']],
    );

    expect($ok)->toBeFalse();
    expect($v->errors())->toHaveKeys(['email', 'age']);
});
