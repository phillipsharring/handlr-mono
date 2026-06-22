<?php

/**
 * Application Routes
 *
 * @var Router $router The router instance provided by the Kernel
 *
 * This file declares the cross-cutting pipe stacks (CORS, session, CSRF, auth)
 * once and exposes them as named JUNCTIONS that service providers can attach
 * routes to. The actual route registrations live in each domain's
 * ServiceProvider::routes() — Auth, Profile, etc. — so adding a new module
 * means dropping a provider into the providers list, not editing this file.
 *
 * Junctions declared here:
 *   - api.basic   → /api with [Cors, VerifyOrigin]
 *                   For stateless endpoints that only need CORS + origin
 *                   verification — no session, no CSRF, no auth (e.g. demo
 *                   endpoints, public health checks).
 *   - api.public  → /api with [Cors, VerifyOrigin, StartSession]
 *                   For session-only public endpoints (e.g. A/B test capture).
 *   - api.session → /api with [Cors, VerifyOrigin, StartSession, RememberMe,
 *                              SessionAuth, CheckBlocked, EnsureCsrf, VerifyCsrf]
 *                   For routes that need the full session/CSRF stack but no
 *                   auth requirement (login, signup, password reset, etc.).
 *   - api.authed  → api.session + RequireAuth
 *                   For authenticated routes (profile, resend-verification).
 *
 * Only routes that don't belong to a domain (e.g. the root view-pipe, or
 * framework-provided ones like A/B testing) live in this file directly.
 */

use Handlr\Ab\Pipes\CaptureAbEvent;
use Handlr\Ab\Pipes\GetAbAssignments;
use App\Auth\CheckBlockedPipe;
use App\Auth\RememberMePipe;
use Handlr\Auth\Pipes\RequireAuthPipe;
use Handlr\Auth\Pipes\SessionAuthPipe;
use Handlr\Auth\Pipes\StartSessionPipe;
use Handlr\Core\Routes\Router;
use Handlr\Csrf\CorsPipe;
use Handlr\Csrf\EnsureCsrfTokenPipe;
use Handlr\Csrf\VerifyCsrfTokenPipe;
use Handlr\Csrf\VerifyOriginPipe;
use Handlr\Pipes\ViewPipe;

/** @var Router $router */

// ── Public pages ──
$router->get('/', [new ViewPipe('home')]);

// ── API pipe stacks + junctions ──
$router->group('/api', [CorsPipe::class, VerifyOriginPipe::class])

    // ── Bare /api (no session, no CSRF, no auth) ──
    // Junction: api.basic — for stateless endpoints that only need the
    // CORS + origin checks the parent /api group already provides.
    ->junction('api.basic')

    // ── Session-only (no auth, no CSRF) ──
    // Junction: api.public
    ->through([StartSessionPipe::class])
        ->junction('api.public')
        ->get('/ab/assignments', [GetAbAssignments::class])
        ->post('/ab/capture', [CaptureAbEvent::class])
    ->end()

    // ── Full session + CSRF (no auth required) ──
    // Junction: api.session — providers attach login/signup/etc. here.
    ->through([
        StartSessionPipe::class,
        RememberMePipe::class,
        SessionAuthPipe::class,
        CheckBlockedPipe::class,
        EnsureCsrfTokenPipe::class,
        VerifyCsrfTokenPipe::class,
    ])
        ->junction('api.session')

        // ── Authenticated routes ──
        // Junction: api.authed — providers attach profile/etc. here.
        ->through([RequireAuthPipe::class])
            ->junction('api.authed')
        ->end()

    ->end()
->end();
