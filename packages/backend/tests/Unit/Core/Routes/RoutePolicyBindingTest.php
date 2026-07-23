<?php

declare(strict_types=1);

use Handlr\Auth\AuthContext;
use Handlr\Core\Container\Container;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Core\Routes\Router;
use Handlr\Database\Record;
use Handlr\Database\Table;
use Handlr\Pipes\Pipe;
use Handlr\Policies\Decision;
use Handlr\Policies\Policy;
use Handlr\Policies\PolicyAction;
use Handlr\Policies\PolicyDenied;
use Handlr\Resolution\RecordNotFound;
use Handlr\Resolution\ResolutionRegistry;
use Handlr\Resolution\Resolver;
use Handlr\Resolution\Resolves;
use Handlr\Resolution\TableResolver;

// ── Fixtures ──

class RpbRecord extends Record {}

/** @extends Table<RpbRecord> */
class RpbTable extends Table
{
    protected string $tableName = 'rpb';
    protected string $recordClass = RpbRecord::class;

    public ?Record $stub = null;

    public function __construct() {}

    public function findById(int|string $id): ?Record
    {
        return $this->stub;
    }
}

enum RpbAction implements PolicyAction
{
    case View;
    case Forbidden;
}

class RpbPolicy implements Policy
{
    public function consult(AuthContext $actor, PolicyAction $action, object $resource): Decision
    {
        return $action === RpbAction::View
            ? Decision::grant()
            : Decision::deny('nope');
    }
}

class RpbHandler implements Pipe
{
    public static bool $built = false;
    public static ?string $seen = null;

    public function __construct(public RpbRecord $record)
    {
        self::$built = true;
    }

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        self::$seen = $this->record->id;
        return $response->withJson(['id' => $this->record->id]);
    }
}

function rpbContainer(?Record $stub): Container
{
    $c = new Container();

    $actor = new AuthContext();
    $actor->setUserId('user-1');
    $c->singleton(AuthContext::class, $actor);

    $c->bind(Resolver::class, TableResolver::class);

    $registry = new ResolutionRegistry();
    $registry->for(RpbRecord::class, RpbTable::class, RpbPolicy::class);
    $c->singleton(ResolutionRegistry::class, $registry);

    $table = new RpbTable();
    $table->stub = $stub;
    $c->singleton(RpbTable::class, $table);

    return $c;
}

function rpbRequest(string $uri): Request
{
    return new Request(
        query: [],
        post: [],
        body: '',
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri],
        headers: []
    );
}

beforeEach(function () {
    RpbHandler::$built = false;
    RpbHandler::$seen = null;
});

// ── Tests ──

it('resolves, consults (grant), binds, and injects the record into the handler', function () {
    $router = new Router(rpbContainer(new RpbRecord(['id' => 'abc'])));
    $router->get('/things/{id}', [RpbHandler::class], new Resolves(RpbRecord::class, RpbAction::View));

    $router->dispatch(rpbRequest('/things/abc'), new Response());

    expect(RpbHandler::$built)->toBeTrue()
        ->and(RpbHandler::$seen)->toBe('abc');
});

it('short-circuits with PolicyDenied (403) on deny and never builds the handler', function () {
    $router = new Router(rpbContainer(new RpbRecord(['id' => 'abc'])));
    $router->get('/things/{id}', [RpbHandler::class], new Resolves(RpbRecord::class, RpbAction::Forbidden));

    expect(fn() => $router->dispatch(rpbRequest('/things/abc'), new Response()))
        ->toThrow(PolicyDenied::class);
    expect(RpbHandler::$built)->toBeFalse();
});

it('throws RecordNotFound (404) when the record is missing and never builds the handler', function () {
    $router = new Router(rpbContainer(null));
    $router->get('/things/{id}', [RpbHandler::class], new Resolves(RpbRecord::class, RpbAction::View));

    expect(fn() => $router->dispatch(rpbRequest('/things/missing'), new Response()))
        ->toThrow(RecordNotFound::class);
    expect(RpbHandler::$built)->toBeFalse();
});

it('resolves and binds without consulting a policy when action is null', function () {
    $router = new Router(rpbContainer(new RpbRecord(['id' => 'xyz'])));
    $router->get('/things/{id}', [RpbHandler::class], new Resolves(RpbRecord::class));

    $router->dispatch(rpbRequest('/things/xyz'), new Response());

    expect(RpbHandler::$seen)->toBe('xyz');
});

it('leaves routes without a Resolves spec untouched (no resolve pipe runs)', function () {
    $router = new Router(rpbContainer(new RpbRecord(['id' => 'abc'])));
    // No spec → no resolve pipe. The handler still autowires a *blank* RpbRecord,
    // so it sees no id — proving nothing resolved the table's stubbed 'abc'.
    $router->get('/plain/{id}', [RpbHandler::class]);

    $router->dispatch(rpbRequest('/plain/abc'), new Response());

    // Handler built with a blank autowired record (its own fresh id), never the
    // table's stubbed 'abc' — so no resolve pipe ran.
    expect(RpbHandler::$built)->toBeTrue()
        ->and(RpbHandler::$seen)->not->toBe('abc');
});
