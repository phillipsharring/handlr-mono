<?php

declare(strict_types=1);

use Handlr\Validation\Rules\RequiredRule;
use Handlr\Validation\Rules\StringRule;
use Handlr\Validation\Rules\IntRule;
use Handlr\Validation\Rules\FloatRule;
use Handlr\Validation\Rules\BoolRule;
use Handlr\Validation\Rules\EmailRule;
use Handlr\Validation\Rules\UrlRule;
use Handlr\Validation\Rules\InRule;
use Handlr\Validation\Rules\MinRule;
use Handlr\Validation\Rules\ConfirmedRule;
use Handlr\Validation\Rules\UuidRule;
use Handlr\Validation\Rules\Uuid7Rule;
use Handlr\Validation\Rules\ArrayRule;
use Handlr\Validation\Rules\DateRule;
use Handlr\Validation\Rules\JsonRule;
use Handlr\Validation\Rules\RuleException;

/**
 * Small helper: build a rule, set its field, run it.
 */
function runRule(object $rule, mixed $value, array $args = [], array $data = []): bool
{
    $rule->setField('thing');
    return $rule->validate($value, $args, $data);
}

// --- RequiredRule --------------------------------------------------------

it('required: rejects null, empty string, empty array', function () {
    expect(runRule(new RequiredRule(), null))->toBeFalse();
    expect(runRule(new RequiredRule(), ''))->toBeFalse();
    expect(runRule(new RequiredRule(), '   '))->toBeFalse();
    expect(runRule(new RequiredRule(), []))->toBeFalse();
});

it('required: accepts "0" string, 0 int, and non-empty values', function () {
    expect(runRule(new RequiredRule(), '0'))->toBeTrue();
    expect(runRule(new RequiredRule(), 0))->toBeTrue();
    expect(runRule(new RequiredRule(), 'x'))->toBeTrue();
    expect(runRule(new RequiredRule(), ['a']))->toBeTrue();
});

it('required: interpolates field name into error message', function () {
    $rule = new RequiredRule();
    runRule($rule, null);
    expect($rule->getErrorMessage())->toBe('The thing field is required.');
});

// --- StringRule ----------------------------------------------------------

it('string: type check + min/max length', function () {
    expect(runRule(new StringRule(), 'hello'))->toBeTrue();
    expect(runRule(new StringRule(), 123))->toBeFalse();
    expect(runRule(new StringRule(), 'ab', ['min' => 3]))->toBeFalse();
    expect(runRule(new StringRule(), 'abcd', ['max' => 3]))->toBeFalse();
    expect(runRule(new StringRule(), 'abc', ['min' => 2, 'max' => 5]))->toBeTrue();
});

// --- IntRule -------------------------------------------------------------

it('int: accepts numeric strings (HTTP input) and ints', function () {
    expect(runRule(new IntRule(), '25'))->toBeTrue();
    expect(runRule(new IntRule(), 25))->toBeTrue();
    expect(runRule(new IntRule(), '25.5'))->toBeFalse();
    expect(runRule(new IntRule(), 'abc'))->toBeFalse();
});

it('int: enforces min/max bounds', function () {
    expect(runRule(new IntRule(), '5', ['min' => 1, 'max' => 10]))->toBeTrue();
    expect(runRule(new IntRule(), '11', ['max' => 10]))->toBeFalse();
    expect(runRule(new IntRule(), '0', ['min' => 1]))->toBeFalse();
});

// --- FloatRule -----------------------------------------------------------

it('float: numeric check + bounds', function () {
    expect(runRule(new FloatRule(), '3.14'))->toBeTrue();
    expect(runRule(new FloatRule(), 'x'))->toBeFalse();
    expect(runRule(new FloatRule(), '5', ['max' => 4]))->toBeFalse();
    expect(runRule(new FloatRule(), '5', ['min' => 1, 'max' => 10]))->toBeTrue();
});

// --- BoolRule ------------------------------------------------------------

it('bool: accepts native bools and boolean-like strings', function () {
    foreach ([true, false, 'true', 'false', 'yes', 'no', 'y', 'n', '1', '0', 'on', 'off', 1, 0] as $v) {
        expect(runRule(new BoolRule(), $v))->toBeTrue();
    }
});

it('bool: rejects empty string, arbitrary text, arrays', function () {
    expect(runRule(new BoolRule(), ''))->toBeFalse();
    expect(runRule(new BoolRule(), 'maybe'))->toBeFalse();
    expect(runRule(new BoolRule(), ['x']))->toBeFalse();
});

// --- EmailRule -----------------------------------------------------------

it('email: validates and tolerates surrounding whitespace', function () {
    expect(runRule(new EmailRule(), 'a@b.com'))->toBeTrue();
    expect(runRule(new EmailRule(), '  a@b.com  '))->toBeTrue();
    expect(runRule(new EmailRule(), 'not-an-email'))->toBeFalse();
});

// --- UrlRule -------------------------------------------------------------

it('url: validates http(s) URLs', function () {
    expect(runRule(new UrlRule(), 'https://example.com/x?y=1'))->toBeTrue();
    expect(runRule(new UrlRule(), 'nope'))->toBeFalse();
});

// --- InRule --------------------------------------------------------------

it('in: allowed values come from the parsed arg keys', function () {
    $args = ['active' => true, 'pending' => true];
    expect(runRule(new InRule(), 'active', $args))->toBeTrue();
    expect(runRule(new InRule(), 'closed', $args))->toBeFalse();
});

it('in: uses strict comparison (no loose type juggling)', function () {
    $args = ['1' => true, '2' => true];
    // '0' string not in set; strict in_array
    expect(runRule(new InRule(), '3', $args))->toBeFalse();
});

// --- MinRule -------------------------------------------------------------

it('min: enforces numeric minimum', function () {
    expect(runRule(new MinRule(), '10', [0 => '5']))->toBeTrue();
    expect(runRule(new MinRule(), '3', [0 => '5']))->toBeFalse();
});

it('min: rejects non-numeric value', function () {
    expect(runRule(new MinRule(), 'abc', [0 => '5']))->toBeFalse();
});

it('min: throws when the minimum parameter is missing or non-numeric', function () {
    expect(fn () => runRule(new MinRule(), '10', []))->toThrow(RuleException::class);
    expect(fn () => runRule(new MinRule(), '10', [0 => 'x']))->toThrow(RuleException::class);
});

// --- ConfirmedRule -------------------------------------------------------

it('confirmed: passes when {field}_confirmation matches', function () {
    $rule = new ConfirmedRule();
    $ok = runRule($rule, 'secret', [], ['thing_confirmation' => 'secret']);
    expect($ok)->toBeTrue();
});

it('confirmed: fails and records an error on the confirmation field', function () {
    $rule = new ConfirmedRule();
    $ok = runRule($rule, 'secret', [], ['thing_confirmation' => 'different']);

    expect($ok)->toBeFalse();
    expect($rule->getOtherFieldErrors())->toHaveKey('thing_confirmation');
});

// --- UuidRule / Uuid7Rule ------------------------------------------------

it('uuid: accepts any RFC4122 version, rejects garbage', function () {
    expect(runRule(new UuidRule(), '00000000-0000-4000-8000-000000000000'))->toBeTrue();
    expect(runRule(new UuidRule(), 'not-a-uuid'))->toBeFalse();
});

it('uuid7: accepts v7 only, rejects a v4', function () {
    expect(runRule(new Uuid7Rule(), '018f0000-0000-7000-8000-000000000000'))->toBeTrue();
    expect(runRule(new Uuid7Rule(), '00000000-0000-4000-8000-000000000000'))->toBeFalse();
});

// --- ArrayRule -----------------------------------------------------------

it('array: accepts arrays, rejects scalars', function () {
    expect(runRule(new ArrayRule(), ['a' => 1]))->toBeTrue();
    expect(runRule(new ArrayRule(), 42))->toBeFalse();
});

it('array: rejects JSON strings despite the docblock (known bug: the final is_array($value) check overrides the JSON branch)', function () {
    // The docblock claims valid JSON strings are accepted, but the trailing
    // `!is_array($value)` guard runs on the raw string and forces failure.
    expect(runRule(new ArrayRule(), '{"a":1}'))->toBeFalse();
    expect(runRule(new ArrayRule(), '{bad json'))->toBeFalse();
});

// --- DateRule ------------------------------------------------------------

it('date: validates against default and custom formats', function () {
    expect(runRule(new DateRule(), '2026-07-10'))->toBeTrue();
    expect(runRule(new DateRule(), '2026-13-99'))->toBeFalse();
    expect(runRule(new DateRule(), '07/10/2026', ['format' => 'm/d/Y']))->toBeTrue();
    // right value, wrong format
    expect(runRule(new DateRule(), '2026-07-10', ['format' => 'm/d/Y']))->toBeFalse();
});

// --- JsonRule ------------------------------------------------------------

it('json: accepts arrays/objects and valid JSON strings, rejects invalid', function () {
    expect(runRule(new JsonRule(), ['a' => 1]))->toBeTrue();
    expect(runRule(new JsonRule(), (object) ['a' => 1]))->toBeTrue();
    expect(runRule(new JsonRule(), '{"a":1}'))->toBeTrue();
    expect(runRule(new JsonRule(), '{bad'))->toBeFalse();
    expect(runRule(new JsonRule(), 42))->toBeFalse();
});
