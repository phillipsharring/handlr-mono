<?php

declare(strict_types=1);

namespace Handlr\Policies;

use Handlr\Auth\AuthContext;

/**
 * Answers "may this actor perform this action on this resource?".
 *
 * One policy per resource type, action-dispatched — the actions over a single
 * resource share context (ownership, collaborators) and belong together. This
 * is object-level authorization: it complements coarse permission checks
 * (RequirePermission), it does not replace them.
 *
 * ```php
 * final class ChecklistPolicy implements Policy
 * {
 *     public function __construct(private CollaboratorsQuery $collaborators) {}
 *
 *     public function consult(AuthContext $actor, PolicyAction $action, object $resource): Decision
 *     {
 *         assert($resource instanceof ChecklistRecord && $action instanceof ChecklistAction);
 *         $uid = $actor->getUserId();
 *         return match ($action) {
 *             ChecklistAction::Delete => $resource->ownedBy($uid)
 *                 ? Decision::grant() : Decision::deny('Only the owner may delete.'),
 *             // ...
 *         };
 *     }
 * }
 * ```
 *
 * @template TResource of object
 */
interface Policy
{
    /**
     * @param TResource $resource The resource being acted upon.
     *
     * @return Decision Grant, or deny with a reason.
     */
    public function consult(AuthContext $actor, PolicyAction $action, object $resource): Decision;
}
