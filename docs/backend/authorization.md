# Authorization: Resolution, Policies & Invariants

Handlr apps are usually **multi-tenant in the shared-table sense**: one database,
many users, each row owned via a `user_id` column. The wall between one user's
data and another's is a code-level check. If that check is something each handler
must *remember* to run, it eventually gets forgotten — and a forgotten ownership
check is an IDOR bug.

This layer makes the check **structural** instead of opt-in. The route declares
what it operates on and who may do it; the framework resolves the record,
authorizes it, and hands it to the handler already-loaded and already-checked. The
handler literally cannot act on an object it didn't receive.

It has three parts, matching three request-time questions:

| Layer | Question | Verb → result | Granularity |
|---|---|---|---|
| **Validation** | is the input well-formed? | `validate` → valid / invalid | per input (see [Validation](./validation)) |
| **Invariant** | can this happen right now? | `check()` → `?Violation` | one class per rule |
| **Policy** | may *this actor* do *this action* on *this object*? | `consult()` → `Grant` \| `Deny` | one class per resource type |

All three run **in the pipe stack, before the handler**. The handler assumes its
input is valid, its preconditions hold, and the actor is permitted.

## Resolution: load the object once

A policy needs the *loaded* object ("may B edit **this** checklist"). Resolution
turns a route `{id}` into its record.

```php
namespace Handlr\Resolution;

interface Resolver
{
    /**
     * @template T of Record
     * @param  Table<T>   $table
     * @return T
     * @throws RecordNotFound   (maps to 404 via the ErrorPipe)
     */
    public function resolve(Table $table, int|string $id): Record;
}
```

The default `TableResolver` is a thin `findById()`-or-throw. `RecordNotFound`
extends `RequestException` (status 404), so the global `ErrorPipe` renders it with
no extra wiring. Resolution is a pure lookup — no auth, no state filtering.

## Policies: may this actor do this?

A policy answers a subject × action × object question. **One policy class per
resource type**, action-dispatched — because the actions over one resource share
context (ownership, collaborator roles), so they belong together.

```php
namespace Handlr\Policies;

/** @template TResource of object */
interface Policy
{
    /** @param TResource $resource */
    public function consult(AuthContext $actor, PolicyAction $action, object $resource): Decision;
}
```

- **`PolicyAction`** is a marker interface implemented by a per-domain enum, so
  actions are typed at the call site (`ChecklistAction::Edit`) — not stringly-typed.
- **`Decision`** is a value object: `Decision::grant()` / `Decision::deny($reason)`,
  with `denied(): bool` and `orDeny(int $status = 403): void`.
- **`PolicyDenied`** (thrown by `orDeny()`) extends `RequestException` (status 403),
  so denials render everywhere the same way — inside a resolve pipe or in a handler.

A worked policy:

```php
enum ChecklistAction implements PolicyAction {
    case View; case Edit; case Delete; case Share; case ManageCollaborators;
}

final class ChecklistPolicy implements Policy
{
    public function __construct(private ChecklistsQuery $query) {}

    public function consult(AuthContext $actor, PolicyAction $action, object $resource): Decision
    {
        assert($resource instanceof ChecklistRecord && $action instanceof ChecklistAction);
        $uid = $actor->getUserId();

        return match ($action) {
            ChecklistAction::View =>
                $this->query->isAccessibleBy($resource->id, $uid) ? Decision::grant() : Decision::deny(),
            ChecklistAction::Edit =>
                $this->query->canEditBy($resource->id, $uid)
                    ? Decision::grant() : Decision::deny('You can only edit lists you own or edit.'),
            ChecklistAction::Delete, ChecklistAction::Share, ChecklistAction::ManageCollaborators =>
                $this->query->isOwnedBy($resource->id, $uid)
                    ? Decision::grant() : Decision::deny('Only the owner can do that.'),
        };
    }
}
```

Policy complements coarse RBAC (`RequirePermission`), it does not replace it —
permissions are *vertical* (does the actor hold a capability), policy is
*horizontal* (may the actor touch this specific row).

## Resolve → consult → bind

The framework wires resolution and policy into a single pipe that runs just before
the handler (after the auth pipes have populated `AuthContext`):

1. **Resolve** the record via its `Table` — missing → `404`.
2. **Consult** the record's policy with the action — denied → `403`.
3. **Bind** the record into the request-scoped container.

The handler then receives it **by type hint** — nothing else to wire:

```php
final class DeleteDeleteChecklist implements Pipe
{
    public function __construct(
        private ChecklistRecord $checklist,   // resolved + authorized, injected
        private ChecklistsTable $table,
        private Presenter $presenter,
    ) {}

    public function handle(Request $req, Response $res, array $args, callable $next): Response
    {
        $this->checklist->deleted_at = date('Y-m-d H:i:s');
        $this->table->update($this->checklist);
        return $res->withJson($this->presenter->success('Checklist deleted.'));
    }
}
```

Because a denying pipe short-circuits, an unauthorized request **never constructs
the handler** (route pipes resolve lazily — see [the request lifecycle](./concepts)).
"Forgot the ownership check" becomes unrepresentable for resolve-bound routes.

### Registering a record's resolution

Tell the framework which `Table` and `Policy` govern a record type, once, in a
service provider's `boot()`:

```php
public function boot(): void
{
    $this->container->get(ResolutionRegistry::class)->for(
        ChecklistRecord::class,   // the record
        ChecklistsTable::class,   // how to load it
        ChecklistPolicy::class,   // how to authorize it
    );
}
```

### Declaring it on routes

Attach resolution/policy to routes with the fluent `resolves()` / `policy()`
modifiers (see [Routing](./routing) for the full grammar, including group-level
defaults):

```php
$router->intoJunction('api.authed')
    ->group('/checklists/{id:uuid}')
        ->resolves(ChecklistRecord::class)->policy(ChecklistAction::View)  // group default
        ->get('', [GetOneChecklist::class])                               // inherits View
        ->patch('', [PatchUpdateChecklist::class])
            ->policy(ChecklistAction::Edit)                               // per-route override
        ->delete('', [DeleteDeleteChecklist::class])
            ->policy(ChecklistAction::Delete)
    ->end();
```

## Invariants: can this happen right now?

An **Invariant** is a single rule that must hold — a quota, a state precondition
("can't share a deleted list", "free plan capped at 10 checklists"). Subject-shaped
but not object-authorization; that's what separates it from a policy.

```php
namespace Handlr\Invariants;

interface Invariant
{
    public function check(): ?Violation;   // null = holds; a Violation = the complaint
}
```

`Violation` carries a `message` and optional `code`. `null` is the boring "fine"
case; a `Violation` is a *thing* that carries why it failed. **One class per rule** —
a `ChecklistUnderItemLimit`, a `ChecklistNotDeleted` — not a bag of `can*` methods.

> [!NOTE] Request invariants vs domain invariants
> These are **request** invariants (quotas, state preconditions — "can this happen
> now?"), checked in the pipe layer. **Domain** invariants ("this aggregate can
> never be in an illegal state") live inside the Record/domain itself, always — not
> here.

## Why this shape

- **Split by cohesion, not by count.** A policy is one class per resource type
  (its actions share context). An invariant is one class per rule (unrelated rules
  bundled together is incidental grouping).
- **`consult` / `Grant` / `Deny`** — you *consult a policy*; it *grants* or *denies*.
  Deliberately not "authorize" (collides with authZ) or Laravel's `Gate`+`Policy`
  split.
- The full rationale, the IDOR bugs that motivated it, and the alternatives
  considered are in **ADR 0004** in the repo (`docs/adr/`).

## See also

- [Routing & Junctions](./routing) — the `resolves()`/`policy()` route grammar.
- [Core Concepts](./concepts) — the request lifecycle and lazy pipe resolution.
- [Database](./database) — `Table` and `Record`, which resolution builds on.
