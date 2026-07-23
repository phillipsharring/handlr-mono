<?php

declare(strict_types=1);

use Handlr\Core\Container\Container;
use Handlr\Core\Container\ContainerInterface;

// ── Fixtures ──

class ScopeServiceFixture
{
    public function __construct(public string $tag = 'autowired') {}
}

class ScopeRecordFixture
{
    public function __construct(public string $id = 'unset') {}
}

class ScopeHandlerFixture
{
    public function __construct(
        public ScopeRecordFixture $record,
        public ScopeServiceFixture $service,
    ) {}
}

// ── Tests ──

it('returns a distinct child from scope()', function () {
    $root = new Container();
    $scope = $root->scope();

    expect($scope)->toBeInstanceOf(ContainerInterface::class)
        ->and($scope)->not->toBe($root);
});

it('reads parent registrations from the child (fall-through)', function () {
    $root = new Container();
    $shared = new ScopeServiceFixture('shared-from-root');
    $root->singleton(ScopeServiceFixture::class, $shared);

    $scope = $root->scope();

    expect($scope->get(ScopeServiceFixture::class))->toBe($shared);
});

it('keeps child writes out of the parent', function () {
    $root = new Container();
    $scope = $root->scope();

    $scope->singleton(ScopeRecordFixture::class, new ScopeRecordFixture('request-only'));

    expect($scope->has(ScopeRecordFixture::class))->toBeTrue()
        // parent has no explicit registration — it would autowire a fresh one
        ->and($root->has(ScopeRecordFixture::class))->toBeFalse()
        ->and($root->get(ScopeRecordFixture::class)->id)->toBe('unset');
});

it('injects a scope-bound instance into an autowired class, parent services still resolve', function () {
    $root = new Container();
    $root->singleton(ScopeServiceFixture::class, new ScopeServiceFixture('shared-from-root'));

    $scope = $root->scope();
    $scope->singleton(ScopeRecordFixture::class, new ScopeRecordFixture('req-123'));

    // ScopeHandlerFixture is unregistered → autowired by the child, so its
    // ScopeRecordFixture dep resolves from the child scope, its service from root.
    $handler = $scope->get(ScopeHandlerFixture::class);

    expect($handler->record->id)->toBe('req-123')
        ->and($handler->service->tag)->toBe('shared-from-root');
});

it('resolves the container/interface type to the scope itself inside a request', function () {
    $root = new Container();
    $scope = $root->scope();

    expect($scope->get(ContainerInterface::class))->toBe($scope)
        ->and($scope->get(Container::class))->toBe($scope);
});

it('does not leak a scope-bound instance across sibling scopes', function () {
    $root = new Container();

    $a = $root->scope();
    $a->singleton(ScopeRecordFixture::class, new ScopeRecordFixture('scope-a'));

    $b = $root->scope();

    expect($a->get(ScopeRecordFixture::class)->id)->toBe('scope-a')
        // b never bound one → autowires a fresh default
        ->and($b->get(ScopeRecordFixture::class)->id)->toBe('unset');
});
