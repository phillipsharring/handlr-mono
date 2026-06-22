<?php

declare(strict_types=1);

namespace App\Profile;

use Handlr\Core\Routes\Router;
use Handlr\Core\ServiceProvider;

/**
 * Owns the /profile routes.
 *
 * Attaches to `api.authed` so each route inherits the full session/CSRF/auth
 * pipe stack from the junction declared in `app/routes.php`.
 */
class ProfileServiceProvider extends ServiceProvider
{
    public function routes(Router $router): void
    {
        $router->intoJunction('api.authed')
            ->group('/profile')
                ->get('',           [GetProfilePipe::class])
                ->patch('/username', [PatchUpdateUsername::class])
                ->patch('/email',    [PatchUpdateEmail::class])
                ->patch('/password', [PatchUpdatePassword::class])
            ->end();
    }
}
