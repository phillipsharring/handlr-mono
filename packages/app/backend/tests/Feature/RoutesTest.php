<?php

declare(strict_types=1);

/**
 * Boots the entire app the same way `public/index.php` does and asserts the
 * resulting route table is exactly what `app/routes.php` and the registered
 * service providers should produce together.
 *
 * Why this exists: routes are now split across `app/routes.php` (junction
 * declarations) and several `ServiceProvider::routes()` methods. This test
 * is the regression check that the refactor — and any future one — preserves
 * the route table without needing a database, an HTTP client, or any external
 * services.
 *
 * Each route is checked for: presence (method + normalized path), full pipe
 * class chain, and correct provider attribution. The conflict detector is
 * exercised separately on a fresh router so the test confirms the framework
 * feature is engaging — not just that the current routes happen not to
 * collide.
 */

use App\Auth\AuthServiceProvider;
use App\Auth\CheckBlockedPipe;
use App\Auth\EmailVerification\GetVerifyEmail;
use App\Auth\EmailVerification\PostResendVerification;
use App\Auth\GetAuthStatus;
use App\Auth\Login\PostLoginAttempt;
use App\Auth\Logout\GetLogout;
use App\Auth\PasswordReset\PostForgotPassword;
use App\Auth\PasswordReset\PostResetPassword;
use App\Auth\RememberMePipe;
use App\Auth\Signup\PostSignup;
use App\Examples\EchoPipe;
use App\Examples\ExampleDetailPipe;
use App\Examples\ExamplesServiceProvider;
use App\Examples\FieldErrorsPipe;
use App\Examples\HelloPipe;
use App\Examples\InvariantErrorPipe;
use App\Examples\SuccessPipe;
use App\Examples\ToastPipe;
use App\Profile\GetProfilePipe;
use App\Profile\PatchUpdateEmail;
use App\Profile\PatchUpdatePassword;
use App\Profile\PatchUpdateUsername;
use App\Profile\ProfileServiceProvider;
use Handlr\Ab\Pipes\CaptureAbEvent;
use Handlr\Ab\Pipes\GetAbAssignments;
use Handlr\Auth\Pipes\RequireAuthPipe;
use Handlr\Auth\Pipes\SessionAuthPipe;
use Handlr\Auth\Pipes\StartSessionPipe;
use Handlr\Core\Kernel;
use Handlr\Core\Routes\Router;
use Handlr\Csrf\CorsPipe;
use Handlr\Csrf\EnsureCsrfTokenPipe;
use Handlr\Csrf\VerifyCsrfTokenPipe;
use Handlr\Csrf\VerifyOriginPipe;

require_once dirname(__DIR__, 1) . '/../bootstrap.php';

// ── Pipe stacks the app declares as junctions in routes.php ──

const STACK_BASIC = [
    CorsPipe::class,
    VerifyOriginPipe::class,
];

const STACK_SESSION_ONLY = [
    CorsPipe::class,
    VerifyOriginPipe::class,
    StartSessionPipe::class,
];

const STACK_SESSION_FULL = [
    CorsPipe::class,
    VerifyOriginPipe::class,
    StartSessionPipe::class,
    RememberMePipe::class,
    SessionAuthPipe::class,
    CheckBlockedPipe::class,
    EnsureCsrfTokenPipe::class,
    VerifyCsrfTokenPipe::class,
];

const STACK_AUTHED = [
    ...STACK_SESSION_FULL,
    RequireAuthPipe::class,
];

/**
 * Holds the booted app state for the file. Pest test closures run in their
 * own scope and don't share file-level locals — a small static cache class
 * is cleaner than globals and works regardless of Pest's execution model.
 */
final class RoutesTestState
{
    public static \Handlr\Core\Container\Container $container;
    /** @var array<string, array{method: string, pattern: string, pipeClasses: array<int,string>}> */
    public static array $byKey;
    /** @var array<string, string> */
    public static array $origins;

    public static function boot(): void
    {
        if (isset(self::$container)) {
            return;
        }

        $app = handlr_app();
        self::$container = $app['container'];

        $router = new Router(self::$container);
        Kernel::getInstance(self::$container, $router, HANDLR_APP_ROOT);

        // Pull internal state out of the Router via Reflection. The Router
        // intentionally doesn't expose a public accessor for $routes — it's
        // internal — but for an introspection test that's exactly what we need.
        $ref = new ReflectionClass($router);

        $routesProp = $ref->getProperty('routes');
        $routesProp->setAccessible(true);
        /** @var array<string, array<int, array{pattern: string, pipes: array}>> $routesByMethod */
        $routesByMethod = $routesProp->getValue($router);

        $originsProp = $ref->getProperty('routeOrigins');
        $originsProp->setAccessible(true);
        self::$origins = $originsProp->getValue($router);

        $byKey = [];
        foreach ($routesByMethod as $method => $methodRoutes) {
            foreach ($methodRoutes as $r) {
                $key = $method . ' ' . $r['pattern'];
                $byKey[$key] = [
                    'method'      => $method,
                    'pattern'     => $r['pattern'],
                    'pipeClasses' => array_map(
                        static fn($p) => is_string($p) ? $p : $p::class,
                        $r['pipes']
                    ),
                ];
            }
        }
        self::$byKey = $byKey;
    }
}

beforeAll(function () {
    RoutesTestState::boot();
});

// ── The expected route table ──

const EXPECTED_API_ROUTES = [
    // [method, path, pipe-stack, owning origin]
    ['GET',   '/api/ab/assignments',           [...STACK_SESSION_ONLY, GetAbAssignments::class],       'app'],
    ['POST',  '/api/ab/capture',               [...STACK_SESSION_ONLY, CaptureAbEvent::class],         'app'],
    ['GET',   '/api/examples/hello',           [...STACK_BASIC, HelloPipe::class],              ExamplesServiceProvider::class],
    ['POST',  '/api/examples/echo',            [...STACK_BASIC, EchoPipe::class],               ExamplesServiceProvider::class],
    ['GET',   '/api/examples/toast',           [...STACK_BASIC, ToastPipe::class],              ExamplesServiceProvider::class],
    ['GET',   '/api/examples/detail',          [...STACK_BASIC, ExampleDetailPipe::class],      ExamplesServiceProvider::class],
    ['POST',  '/api/examples/field-errors',    [...STACK_BASIC, FieldErrorsPipe::class],        ExamplesServiceProvider::class],
    ['POST',  '/api/examples/invariant-error', [...STACK_BASIC, InvariantErrorPipe::class],     ExamplesServiceProvider::class],
    ['POST',  '/api/examples/success',         [...STACK_BASIC, SuccessPipe::class],            ExamplesServiceProvider::class],
    ['GET',   '/api/auth/me',                  [...STACK_SESSION_FULL, GetAuthStatus::class],          AuthServiceProvider::class],
    ['POST',  '/api/auth/login',               [...STACK_SESSION_FULL, PostLoginAttempt::class],      AuthServiceProvider::class],
    ['POST',  '/api/auth/signup',              [...STACK_SESSION_FULL, PostSignup::class],            AuthServiceProvider::class],
    ['GET',   '/api/auth/logout',              [...STACK_SESSION_FULL, GetLogout::class],             AuthServiceProvider::class],
    ['POST',  '/api/auth/forgot-password',     [...STACK_SESSION_FULL, PostForgotPassword::class],    AuthServiceProvider::class],
    ['POST',  '/api/auth/reset-password',      [...STACK_SESSION_FULL, PostResetPassword::class],     AuthServiceProvider::class],
    ['GET',   '/api/auth/verify-email',        [...STACK_SESSION_FULL, GetVerifyEmail::class],        AuthServiceProvider::class],
    ['POST',  '/api/auth/resend-verification', [...STACK_AUTHED,       PostResendVerification::class], AuthServiceProvider::class],
    ['GET',   '/api/profile',                  [...STACK_AUTHED,       GetProfilePipe::class],         ProfileServiceProvider::class],
    ['PATCH', '/api/profile/username',         [...STACK_AUTHED,       PatchUpdateUsername::class],   ProfileServiceProvider::class],
    ['PATCH', '/api/profile/email',            [...STACK_AUTHED,       PatchUpdateEmail::class],      ProfileServiceProvider::class],
    ['PATCH', '/api/profile/password',         [...STACK_AUTHED,       PatchUpdatePassword::class],   ProfileServiceProvider::class],
];

// ── Tests ──

it('boots the app without throwing', function () {
    expect(RoutesTestState::$byKey)->not->toBeEmpty();
});

it('registers the root view-pipe route', function () {
    expect(RoutesTestState::$byKey)->toHaveKey('GET /');
});

it('registers every expected API route with the correct pipe chain', function () {
    foreach (EXPECTED_API_ROUTES as [$method, $path, $expectedPipes, $_origin]) {
        $key = "$method $path";

        expect(RoutesTestState::$byKey)->toHaveKey($key);
        expect(RoutesTestState::$byKey[$key]['pipeClasses'])->toBe($expectedPipes);
    }
});

it('attributes each route to the correct origin (app or provider)', function () {
    foreach (EXPECTED_API_ROUTES as [$method, $path, $_pipes, $expectedOrigin]) {
        $key = "$method $path";
        expect(RoutesTestState::$origins)->toHaveKey($key);
        expect(RoutesTestState::$origins[$key])->toBe($expectedOrigin);
    }
});

it('does not register any routes beyond the expected set', function () {
    $expectedKeys = array_map(
        static fn($e) => $e[0] . ' ' . $e[1],
        EXPECTED_API_ROUTES
    );
    $expectedKeys[] = 'GET /'; // root view-pipe, intentionally omitted from EXPECTED_API_ROUTES

    $unexpected = array_diff(array_keys(RoutesTestState::$byKey), $expectedKeys);
    expect($unexpected)->toBe([]);
});

it('throws on a duplicate route registration (conflict detector engaged)', function () {
    $freshRouter = new Router(RoutesTestState::$container);
    $freshRouter->get('/dupe', []);

    expect(fn() => $freshRouter->get('/dupe', []))
        ->toThrow(RuntimeException::class, 'already registered');
});
