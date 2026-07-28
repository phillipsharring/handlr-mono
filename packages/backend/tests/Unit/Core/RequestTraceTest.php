<?php

declare(strict_types=1);

use Handlr\Core\Container\Container;
use Handlr\Core\Request;
use Handlr\Core\RequestTrace;
use Handlr\Core\Response;
use Handlr\Core\Routes\Router;
use Handlr\Pipes\Pipe;

class PingPipe implements Pipe
{
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        return $response->withJson(['ok' => true]);
    }
}

function rtRequest(string $uri): Request
{
    return new Request(
        query: [],
        post: [],
        body: '',
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri],
        headers: []
    );
}

it('generates a UUIDv7 id on construction', function () {
    $trace = new RequestTrace();
    expect($trace->getId())
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('begin() sets a fresh id, method, path and clears the actor', function () {
    $trace = new RequestTrace();
    $first = $trace->getId();
    $trace->setActor('user-1');

    $trace->begin('POST', '/things');

    expect($trace->getId())->not->toBe($first)
        ->and($trace->getMethod())->toBe('POST')
        ->and($trace->getPath())->toBe('/things')
        ->and($trace->getActor())->toBeNull();
});

it('tracks the actor once set', function () {
    $trace = new RequestTrace();
    $trace->setActor('user-42');
    expect($trace->getActor())->toBe('user-42');
});

it('dispatch resets the trace and stamps X-Request-Id on the response', function () {
    $c = new Container();
    $c->singleton(RequestTrace::class, $trace = new RequestTrace());
    $router = new Router($c);
    $router->get('/ping', [PingPipe::class]);

    $res = $router->dispatch(rtRequest('/ping'), new Response());

    expect($trace->getMethod())->toBe('GET')
        ->and($trace->getPath())->toBe('/ping')
        ->and($res->getHeader('X-Request-Id'))->toBe($trace->getId());
});

it('stamps X-Request-Id even on a 404', function () {
    $c = new Container();
    $c->singleton(RequestTrace::class, $trace = new RequestTrace());
    $router = new Router($c);

    $res = $router->dispatch(rtRequest('/nope'), new Response());

    expect($res->getStatusCode())->toBe(404)
        ->and($res->getHeader('X-Request-Id'))->toBe($trace->getId());
});
