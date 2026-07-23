<?php

declare(strict_types=1);

use Handlr\Core\Container\Container;
use Handlr\Core\Container\ContainerInterface;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Core\Routes\Router;
use Handlr\Pipes\Pipe;

// ── Fixtures ──

/** The thing a resolve-pipe binds into the request scope. */
class ScopeBoundThing
{
    public function __construct(public string $id = 'unresolved') {}
}

/** Resolves the route id and binds a ScopeBoundThing into the request scope. */
class BindThingPipe implements Pipe
{
    // The container/interface type resolves to the request scope itself.
    public function __construct(private ContainerInterface $scope) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $this->scope->singleton(ScopeBoundThing::class, new ScopeBoundThing($args['id'] ?? 'none'));
        return $next($request, $response, $args);
    }
}

/** Downstream handler that receives the bound thing purely by type hint. */
class EchoThingHandler implements Pipe
{
    public static bool $constructed = false;
    public static ?string $seenId = null;

    public function __construct(public ScopeBoundThing $thing)
    {
        self::$constructed = true;
    }

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        self::$seenId = $this->thing->id;
        return $response->withJson(['id' => $this->thing->id]);
    }
}

/** Short-circuits with a 403 without calling $next. */
class ScopeDenyPipe implements Pipe
{
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        return $response->withStatus(Response::HTTP_FORBIDDEN)->withJson(['denied' => true]);
    }
}

function makeScopeRequest(string $method, string $uri): Request
{
    return new Request(
        query: [],
        post: [],
        body: '',
        server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
        headers: []
    );
}

beforeEach(function () {
    EchoThingHandler::$constructed = false;
    EchoThingHandler::$seenId = null;
});

// ── Tests ──

it('binds a resolved record into the scope and injects it into the handler by type hint', function () {
    $router = new Router(new Container());
    $router->get('/things/{id}', [BindThingPipe::class, EchoThingHandler::class]);

    $router->dispatch(makeScopeRequest('GET', '/things/abc'), new Response());

    expect(EchoThingHandler::$constructed)->toBeTrue()
        ->and(EchoThingHandler::$seenId)->toBe('abc');
});

it('never constructs the handler when an upstream pipe denies (lazy resolution)', function () {
    $router = new Router(new Container());
    $router->get('/things/{id}', [ScopeDenyPipe::class, EchoThingHandler::class]);

    $router->dispatch(makeScopeRequest('GET', '/things/xyz'), new Response());

    expect(EchoThingHandler::$constructed)->toBeFalse()
        ->and(EchoThingHandler::$seenId)->toBeNull();
});

it('isolates scope-bound state between two dispatches', function () {
    $router = new Router(new Container());
    $router->get('/things/{id}', [BindThingPipe::class, EchoThingHandler::class]);

    $router->dispatch(makeScopeRequest('GET', '/things/first'), new Response());
    expect(EchoThingHandler::$seenId)->toBe('first');

    $router->dispatch(makeScopeRequest('GET', '/things/second'), new Response());
    expect(EchoThingHandler::$seenId)->toBe('second');
});
