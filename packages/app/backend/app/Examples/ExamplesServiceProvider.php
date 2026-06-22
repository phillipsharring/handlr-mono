<?php

declare(strict_types=1);

namespace App\Examples;

use Handlr\Core\Routes\Router;
use Handlr\Core\ServiceProvider;

/**
 * Wires up a small set of demo endpoints under /api/examples.
 *
 * The pipes are intentionally trivial — they exist so a fresh app has
 * working endpoints to point HTMX, fetch, or curl at while exercising
 * the response/error/toast/field-error patterns the framework provides.
 *
 * Routes attach to the `api.basic` junction declared in `app/routes.php`,
 * which gives them just CORS + VerifyOrigin — no session, no CSRF, no
 * auth. The example endpoints are stateless, so the bare junction is
 * the right fit; dropping this provider into `config.php` is the only
 * wiring step.
 *
 * To remove the examples module: delete `app/Examples/` and remove
 * `App\Examples\ExamplesServiceProvider::class` from the providers list
 * in `app/config.php`. Nothing else references this module.
 */
class ExamplesServiceProvider extends ServiceProvider
{
    public function routes(Router $router): void
    {
        $router->intoJunction('api.basic')
            ->group('/examples')
                ->get('/hello',           [HelloPipe::class])
                ->post('/echo',           [EchoPipe::class])
                ->get('/toast',           [ToastPipe::class])
                ->get('/detail',          [ExampleDetailPipe::class])
                ->post('/field-errors',   [FieldErrorsPipe::class])
                ->post('/invariant-error', [InvariantErrorPipe::class])
                ->post('/success',        [SuccessPipe::class])
            ->end();
    }
}
