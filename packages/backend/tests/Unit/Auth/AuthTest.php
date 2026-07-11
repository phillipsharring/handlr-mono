<?php

declare(strict_types=1);

use Handlr\Auth\AuthContext;
use Handlr\Auth\AuthorizedUser;
use Handlr\Auth\AuthorizationService;
use Handlr\Auth\PermissionsProviderInterface;
use Handlr\Auth\UnauthorizedException;

// --- AuthContext ---------------------------------------------------------

it('auth context starts unauthenticated', function () {
    $ctx = new AuthContext();
    expect($ctx->isAuthenticated())->toBeFalse();
    expect($ctx->getUserId())->toBeNull();
});

it('auth context reports authenticated once a user id is set', function () {
    $ctx = new AuthContext();
    $ctx->setUserId('u-1');
    expect($ctx->isAuthenticated())->toBeTrue();
    expect($ctx->getUserId())->toBe('u-1');
});

// --- AuthorizedUser ------------------------------------------------------

it('authorized user reports roles and permissions', function () {
    $u = new AuthorizedUser('u-1', ['admin'], ['posts.edit', 'posts.delete']);

    expect($u->id())->toBe('u-1');
    expect($u->hasRole('admin'))->toBeTrue();
    expect($u->hasRole('guest'))->toBeFalse();
    expect($u->hasPermission('posts.edit'))->toBeTrue();
    expect($u->hasPermission('posts.publish'))->toBeFalse();
    expect($u->getRoles())->toBe(['admin']);
    expect($u->getPermissions())->toBe(['posts.edit', 'posts.delete']);
});

it('authorized user uses strict membership checks', function () {
    $u = new AuthorizedUser('u-1', ['1'], []);
    // '1' role must not match integer 1 loosely
    expect($u->hasRole('2'))->toBeFalse();
});

// --- AuthorizationService ------------------------------------------------

it('service returns null subject when unauthenticated', function () {
    $ctx = new AuthContext();
    $provider = $this->createMock(PermissionsProviderInterface::class);
    $provider->expects($this->never())->method('getRolesForUser');

    $svc = new AuthorizationService($ctx, $provider);

    expect($svc->subject())->toBeNull();
});

it('service builds a subject from the provider when authenticated', function () {
    $ctx = new AuthContext();
    $ctx->setUserId('u-9');

    $provider = $this->createMock(PermissionsProviderInterface::class);
    $provider->method('getRolesForUser')->with('u-9')->willReturn(['editor']);
    $provider->method('getPermissionsForUser')->with('u-9')->willReturn(['posts.edit']);

    $svc = new AuthorizationService($ctx, $provider);
    $subject = $svc->subject();

    expect($subject)->toBeInstanceOf(AuthorizedUser::class);
    expect($subject->id())->toBe('u-9');
    expect($subject->hasRole('editor'))->toBeTrue();
    expect($subject->hasPermission('posts.edit'))->toBeTrue();
});

it('service caches the subject (provider hit once across calls)', function () {
    $ctx = new AuthContext();
    $ctx->setUserId('u-9');

    $provider = $this->createMock(PermissionsProviderInterface::class);
    $provider->expects($this->once())->method('getRolesForUser')->willReturn([]);
    $provider->expects($this->once())->method('getPermissionsForUser')->willReturn([]);

    $svc = new AuthorizationService($ctx, $provider);

    $first = $svc->subject();
    $second = $svc->subject();

    expect($second)->toBe($first); // same instance
});

it('require() returns the subject when authenticated', function () {
    $ctx = new AuthContext();
    $ctx->setUserId('u-9');

    $provider = $this->createMock(PermissionsProviderInterface::class);
    $provider->method('getRolesForUser')->willReturn([]);
    $provider->method('getPermissionsForUser')->willReturn([]);

    $svc = new AuthorizationService($ctx, $provider);

    expect($svc->require()->id())->toBe('u-9');
});

it('require() throws UnauthorizedException when unauthenticated', function () {
    $ctx = new AuthContext();
    $provider = $this->createMock(PermissionsProviderInterface::class);
    $svc = new AuthorizationService($ctx, $provider);

    expect(fn () => $svc->require())->toThrow(UnauthorizedException::class);
});

it('UnauthorizedException defaults to a 401 code', function () {
    $e = new UnauthorizedException();
    expect($e->getCode())->toBe(401);
    expect($e->getMessage())->toBe('Unauthorized');
});
