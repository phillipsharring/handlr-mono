<?php

declare(strict_types=1);

namespace Handlr\Policies;

/**
 * Marker for a domain's policy actions.
 *
 * Implement it with a per-domain enum so actions are typed at the call site
 * (`ChecklistAction::Edit`) rather than stringly-typed, while still being
 * dispatchable from a route/junction.
 *
 * ```php
 * enum ChecklistAction implements PolicyAction
 * {
 *     case View;
 *     case Edit;
 *     case Archive;
 *     case Share;
 *     case Delete;
 * }
 * ```
 */
interface PolicyAction {}
