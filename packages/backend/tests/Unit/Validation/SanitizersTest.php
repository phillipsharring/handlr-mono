<?php

declare(strict_types=1);

use Handlr\Validation\Sanitizers\SanitizerFactory;
use Handlr\Validation\Sanitizers\StringSanitizer;
use Handlr\Validation\Sanitizers\IntSanitizer;
use Handlr\Validation\Sanitizers\FloatSanitizer;
use Handlr\Validation\Sanitizers\BoolSanitizer;
use Handlr\Validation\Sanitizers\EmailSanitizer;
use Handlr\Validation\Sanitizers\UrlSanitizer;
use Handlr\Validation\Sanitizers\OutputSanitizer;
use Handlr\Validation\ValidationException;

// --- StringSanitizer -----------------------------------------------------

it('string: strips control characters always', function () {
    $out = (new StringSanitizer())->sanitize("a\x00b\x1Fc");
    expect($out)->toBe('abc');
});

it('string: trims and strips tags when flagged', function () {
    $s = new StringSanitizer();
    expect($s->sanitize('  hi  ', ['trim' => true]))->toBe('hi');
    expect($s->sanitize('<b>hi</b>', ['strip_tags' => true]))->toBe('hi');
});

// --- IntSanitizer --------------------------------------------------------

it('int: truncates decimals instead of concatenating digits', function () {
    // 123.45 must become 123, NOT 12345
    expect((new IntSanitizer())->sanitize('123.45'))->toBe(123);
    expect((new IntSanitizer())->sanitize('99 bottles'))->toBe(99);
});

// --- FloatSanitizer ------------------------------------------------------

it('float: preserves decimal point', function () {
    expect((new FloatSanitizer())->sanitize('12.50'))->toBe(12.5);
    expect((new FloatSanitizer())->sanitize('abc'))->toBe(0.0);
});

// --- BoolSanitizer -------------------------------------------------------

it('bool: maps y/n and standard boolean strings', function () {
    $b = new BoolSanitizer();
    expect($b->sanitize('y'))->toBeTrue();
    expect($b->sanitize('n'))->toBeFalse();
    expect($b->sanitize('true'))->toBeTrue();
    expect($b->sanitize('off'))->toBeFalse();
    expect($b->sanitize([]))->toBeFalse();
});

// --- EmailSanitizer ------------------------------------------------------

it('email: trims but does not change case', function () {
    expect((new EmailSanitizer())->sanitize('  Foo@Bar.com '))->toBe('Foo@Bar.com');
});

// --- UrlSanitizer --------------------------------------------------------

it('url: removes illegal characters', function () {
    expect((new UrlSanitizer())->sanitize('https://a.com/x y'))->toBe('https://a.com/xy');
});

// --- OutputSanitizer (context escaping) ----------------------------------

it('output html: escapes quotes and angle brackets', function () {
    expect(OutputSanitizer::html('<a href="x">'))
        ->toBe('&lt;a href=&quot;x&quot;&gt;');
});

it('output url: raw-url-encodes', function () {
    expect(OutputSanitizer::url('a b&c'))->toBe('a%20b%26c');
});

it('output js: backslash-escapes quotes', function () {
    expect(OutputSanitizer::js('he said "hi"'))->toBe('he said \\"hi\\"');
});

it('output pdf: normalizes smart quotes and strips non-ASCII', function () {
    // U+201C/U+201D curly quotes -> straight; emoji stripped
    $in = "\xE2\x80\x9Chi\xE2\x80\x9D\xF0\x9F\x98\x80";
    expect(OutputSanitizer::pdf($in))->toBe('"hi"');
});

it('output pdf: maps recursively over arrays', function () {
    expect(OutputSanitizer::pdf(["a\xF0\x9F\x98\x80", 'b']))->toBe(['a', 'b']);
});

// --- SanitizerFactory ----------------------------------------------------

it('factory: resolves known sanitizers by type name', function () {
    expect((new SanitizerFactory())->create('string'))->toBeInstanceOf(StringSanitizer::class);
    expect((new SanitizerFactory())->create('int'))->toBeInstanceOf(IntSanitizer::class);
});

it('factory: throws for an unknown sanitizer type', function () {
    expect(fn () => (new SanitizerFactory())->create('nope'))
        ->toThrow(ValidationException::class);
});
