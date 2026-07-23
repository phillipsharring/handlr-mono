<?php

declare(strict_types=1);

namespace Handlr\Resolution;

use Handlr\Policies\PolicyAction;

/**
 * Route metadata declaring what a route resolves, and optionally the policy
 * action to consult before the handler runs.
 *
 * Attach it to a route:
 *
 * ```php
 * $router->delete('/checklists/{id:uuid}', [DeleteChecklist::class],
 *     new Resolves(ChecklistRecord::class, ChecklistAction::Delete));
 * ```
 *
 * The record's Table and Policy are looked up from the {@see ResolutionRegistry},
 * so routes only name the record type and the action.
 */
final class Resolves
{
    /**
     * @param class-string      $record The record type to resolve and bind.
     * @param PolicyAction|null  $action When set, the record's policy is consulted
     *                                   with it and a denial short-circuits the request.
     * @param string             $param  Route param holding the id (default 'id').
     */
    public function __construct(
        public readonly string $record,
        public readonly ?PolicyAction $action = null,
        public readonly string $param = 'id',
    ) {}
}
