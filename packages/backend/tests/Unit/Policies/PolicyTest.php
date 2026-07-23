<?php

declare(strict_types=1);

use Handlr\Auth\AuthContext;
use Handlr\Policies\Decision;
use Handlr\Policies\Policy;
use Handlr\Policies\PolicyAction;
use Handlr\Policies\PolicyDenied;

// ── Fixtures: a domain action enum + a policy over a resource ──

enum PolicyTestAction implements PolicyAction
{
    case View;
    case Delete;
}

class PolicyTestResource
{
    public function __construct(public string $ownerId) {}
}

/**
 * @implements Policy<PolicyTestResource>
 */
class PolicyTestPolicy implements Policy
{
    public function consult(AuthContext $actor, PolicyAction $action, object $resource): Decision
    {
        assert($resource instanceof PolicyTestResource);
        $isOwner = $resource->ownerId === $actor->getUserId();

        return match ($action) {
            PolicyTestAction::View   => Decision::grant(),
            PolicyTestAction::Delete => $isOwner
                ? Decision::grant()
                : Decision::deny('Only the owner may delete.'),
            default                  => Decision::deny(),
        };
    }
}

function actorWithId(?string $id): AuthContext
{
    $ctx = new AuthContext();
    if ($id !== null) {
        $ctx->setUserId($id);
    }
    return $ctx;
}

// ── Decision ──

it('grant() is granted and not denied', function () {
    $d = Decision::grant();
    expect($d->granted)->toBeTrue()->and($d->denied())->toBeFalse();
});

it('deny() is denied and carries its reason', function () {
    $d = Decision::deny('nope');
    expect($d->granted)->toBeFalse()
        ->and($d->denied())->toBeTrue()
        ->and($d->reason)->toBe('nope');
});

it('orDeny() is a no-op when granted', function () {
    Decision::grant()->orDeny();
    expect(true)->toBeTrue(); // reached without throwing
});

it('orDeny() throws PolicyDenied with the status and reason when denied', function () {
    $caught = null;
    try {
        Decision::deny('blocked')->orDeny(404);
    } catch (PolicyDenied $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(PolicyDenied::class)
        ->and($caught->getMessage())->toBe('blocked')
        ->and($caught->status())->toBe(404);
});

// ── Policy dispatch ──

it('grants the owner and denies a stranger for the same action', function () {
    $policy = new PolicyTestPolicy();
    $resource = new PolicyTestResource(ownerId: 'user-a');

    $owner   = $policy->consult(actorWithId('user-a'), PolicyTestAction::Delete, $resource);
    $stranger = $policy->consult(actorWithId('user-b'), PolicyTestAction::Delete, $resource);

    expect($owner->granted)->toBeTrue()
        ->and($stranger->denied())->toBeTrue()
        ->and($stranger->reason)->toBe('Only the owner may delete.');
});

it('grants a read to a stranger', function () {
    $policy = new PolicyTestPolicy();
    $resource = new PolicyTestResource(ownerId: 'user-a');

    expect($policy->consult(actorWithId('user-b'), PolicyTestAction::View, $resource)->granted)
        ->toBeTrue();
});
