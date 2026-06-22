<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Data\UserPermissionsQuery;
use App\Auth\EmailVerification\GetVerifyEmail;
use App\Auth\EmailVerification\PostResendVerification;
use App\Auth\EmailVerification\SendVerificationEmailListener;
use App\Auth\Login\Listeners\UpdateLastLoginListener;
use App\Auth\Login\PostLoginAttempt;
use App\Auth\Logout\GetLogout;
use App\Auth\PasswordReset\PostForgotPassword;
use App\Auth\PasswordReset\PostResetPassword;
use App\Auth\Signup\PostSignup;
use Handlr\Auth\AuthContext;
use Handlr\Auth\PermissionsProviderInterface;
use Handlr\Core\Routes\Router;
use Handlr\Core\ServiceProvider;

/**
 * Wires up the Auth domain: container bindings, event listeners, and routes.
 *
 * Routes attach to two junctions declared in `app/routes.php`:
 *   - `api.session` for the public-but-needs-session endpoints (login,
 *     signup, logout, /me, password reset, email verification)
 *   - `api.authed` for routes requiring an authenticated user (resend
 *     email verification)
 *
 * Each junction inherits its full pipe stack from the host declaration; this
 * provider does not redeclare CORS / session / CSRF / RequireAuth pipes.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AuthContext::class);
        $this->container->bind(PermissionsProviderInterface::class, UserPermissionsQuery::class);
    }

    public function events(): array
    {
        return [
            'user.signed_up' => [SendVerificationEmailListener::class],
            'user.logged_in' => [UpdateLastLoginListener::class],
        ];
    }

    public function routes(Router $router): void
    {
        // Public-but-needs-session: login, signup, logout, password reset, etc.
        $router->intoJunction('api.session')
            ->group('/auth')
                ->get('/me',              [GetAuthStatus::class])
                ->post('/login',          [PostLoginAttempt::class])
                ->post('/signup',         [PostSignup::class])
                ->get('/logout',          [GetLogout::class])
                ->post('/forgot-password', [PostForgotPassword::class])
                ->post('/reset-password', [PostResetPassword::class])
                ->get('/verify-email',    [GetVerifyEmail::class])
            ->end();

        // Authenticated: requires a logged-in user.
        $router->intoJunction('api.authed')
            ->post('/auth/resend-verification', [PostResendVerification::class]);
    }
}
