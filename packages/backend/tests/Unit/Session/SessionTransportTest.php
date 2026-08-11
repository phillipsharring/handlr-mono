<?php

declare(strict_types=1);

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Session\ChainSessionTransport;
use Handlr\Session\CookieTransport;
use Handlr\Session\HeaderTransport;

/**
 * @param array<string, string> $headers
 */
function transportRequest(array $headers = []): Request
{
    return new Request([], [], '', [], $headers);
}

// --- CookieTransport: transparent, defers to PHP on both ends ---------------

it('cookie transport extracts null so PHP reads the cookie natively', function () {
    $t = new CookieTransport();

    expect($t->extract(transportRequest(['X-Session-Id' => 'abc'])))->toBeNull();
});

it('cookie transport emit is a pass-through', function () {
    $t = new CookieTransport();
    $response = new Response();

    expect($t->emit($response, 'abc123'))->toBe($response);
});

// --- HeaderTransport: extract in ------------------------------------------

it('header transport extracts the configured header', function () {
    $t = new HeaderTransport();

    expect($t->extract(transportRequest(['X-Session-Id' => 'abc123'])))->toBe('abc123');
});

it('header transport extracts null when the header is absent', function () {
    $t = new HeaderTransport();

    expect($t->extract(transportRequest()))->toBeNull();
});

it('header transport rejects a malformed id (fixation guard)', function () {
    $t = new HeaderTransport();

    expect($t->extract(transportRequest(['X-Session-Id' => 'bad id!; DROP'])))->toBeNull();
});

it('header transport parses a bearer token', function () {
    $t = new HeaderTransport('Authorization', bearer: true);

    expect($t->extract(transportRequest(['Authorization' => 'Bearer tok3n-Value'])))
        ->toBe('tok3n-Value');
});

it('header transport ignores a non-bearer Authorization header when bearer expected', function () {
    $t = new HeaderTransport('Authorization', bearer: true);

    expect($t->extract(transportRequest(['Authorization' => 'Basic Zm9v'])))->toBeNull();
});

// --- HeaderTransport: emit out (id echoed on the plain header name) ---------

it('header transport emits the id on the header, without the Bearer prefix', function () {
    $t = new HeaderTransport('Authorization', bearer: true);

    $response = $t->emit(new Response(), 'abc123');

    expect($response->getHeader('Authorization'))->toBe('abc123');
});

it('header transport emit is a no-op for an empty id', function () {
    $t = new HeaderTransport();
    $response = new Response();

    expect($t->emit($response, ''))->toBe($response);
});

// --- ChainSessionTransport: order + emit-in-kind ---------------------------

it('chain extract returns the first non-null id', function () {
    $t = new ChainSessionTransport(new HeaderTransport(), new CookieTransport());

    expect($t->extract(transportRequest()))->toBeNull()          // header absent, cookie null
        ->and($t->extract(transportRequest(['X-Session-Id' => 'fromheader'])))->toBe('fromheader');
});

it('chain emits in the modality that matched inbound', function () {
    $t = new ChainSessionTransport(new HeaderTransport(), new CookieTransport());

    // A header request matched → id is echoed back on the header.
    $t->extract(transportRequest(['X-Session-Id' => 'abc123']));
    expect($t->emit(new Response(), 'abc123')->getHeader('X-Session-Id'))->toBe('abc123');
});

it('chain does not leak the id on a header when the cookie transport matched', function () {
    $t = new ChainSessionTransport(new HeaderTransport(), new CookieTransport());
    $response = new Response();

    // No header inbound → cookie transport matched (null id) → emit is pass-through,
    // so a browser response never carries the session id in a readable header.
    $t->extract(transportRequest());
    $emitted = $t->emit($response, 'abc123');

    expect($emitted->getHeader('X-Session-Id'))->toBeNull();
});
