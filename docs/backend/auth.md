# Auth

Handlr ships **session-based authentication** and **coarse permission checks** as
pipes. Fine-grained, per-object authorization is a separate layer —
[Policies](./authorization) — because "is this user logged in / an admin" and "may
this user edit *this* row" are different questions.

## The two axes

| Axis | Question | Mechanism |
|---|---|---|
| **Authentication** | who is this? | session pipes → `AuthContext` |
| **Coarse authorization** | does the actor hold a capability? | `RequirePermissionPipe` + permissions |
| **Object authorization** | may the actor touch *this* record? | [Policy](./authorization) |

## AuthContext

`AuthContext` (`src/Auth/AuthContext.php`) is the request-scoped "who is logged in":

```php
$ctx->setUserId('user-uuid');
$ctx->getUserId();          // ?string
$ctx->isAuthenticated();    // bool
```

It's populated by `SessionAuthPipe` and injected into policies and handlers by type.

## Session pipes

Wire these into a junction so a family of routes shares them (see [Routing](./routing)):

- **`StartSessionPipe`** — starts the session (DB-backed via `DatabaseSessionDriver`).
- **`SessionAuthPipe`** — reads `user_id` from the session and populates `AuthContext`.
  It does **not** reject anonymous requests; it just records who (if anyone) is here.
- **`RequireAuthPipe`** — rejects anonymous requests with a `401` JSON `{error:"Unauthorized"}`.
  It never redirects, which keeps it HTMX-safe (the frontend intercepts the 401 and
  shows a login modal — see [Auth State](/frontend/auth-state)).

```php
$router->group('/api', [CorsPipe::class])
    ->through([StartSessionPipe::class, SessionAuthPipe::class])
        ->junction('api.public')                          // knows the user, allows anon
        ->through([RequireAuthPipe::class])
            ->junction('api.authed')                      // logged-in only
        ->end()
    ->end()
->end();
```

## Permissions (coarse RBAC)

For capability checks, add a permission pipe:

- **`RequirePermissionPipe(string|array $permissions)`** — passes if the actor has
  **any** of the listed permissions.
- **`RequireAllPermissionsPipe(...)`** — requires **all** of them.

Missing subject → `401`; present but lacking the permission → `403`. Run them after
`SessionAuthPipe`.

```php
->group('/admin', [new RequirePermissionPipe('admin.access')])
    ->junction('api.admin')
->end()
```

### Bridging your schema

The framework doesn't own your roles/permissions tables — your app bridges them:

- **`PermissionsProviderInterface`** — implement `getRolesForUser(string $userId): array`
  and `getPermissionsForUser(string $userId): array` to map your schema to the
  framework.
- **`AuthorizationService`** — `subject(): ?AuthSubject` resolves and caches the
  current actor; `require(): AuthSubject` returns it or throws `UnauthorizedException`.
- **`AuthSubject`** (interface: `id()`, `hasRole($role)`, `hasPermission($perm)`) — the
  resolved actor; `AuthorizedUser` is the default implementation.

Register your `PermissionsProviderInterface` implementation in a service provider's
`register()`, and the permission pipes use it automatically.

## Login / logout flow

Login is an ordinary handler: verify the credentials, then set the user id on the
session (which `SessionAuthPipe` reads on subsequent requests). Logout destroys the
session. Because sessions are DB-backed, they survive across worker processes and can
be invalidated server-side. The frontend keeps its own cached view of auth state and
resyncs on demand — see [Auth State](/frontend/auth-state).

## CSRF

Session auth pairs with CSRF protection. The CSRF pipes (`EnsureCsrfTokenPipe`,
`VerifyCsrfTokenPipe`, `VerifyOriginPipe`) live alongside the auth pipes in the same
junctions — covered in [CSRF](/frontend/csrf), which documents both the backend pipes
and the frontend token handling.

## See also

- [Authorization](./authorization) — per-object policies (the horizontal axis).
- [Routing & Junctions](./routing) — composing the auth pipe stack once.
- [CSRF](/frontend/csrf) — the token pipes that run beside auth.
