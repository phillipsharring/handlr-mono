# Auth

> [!NOTE] Draft
> This page is a stub. Content coming.

## To cover

- **AuthContext** — current user id / authenticated state
- **AuthorizationService** — resolves an `AuthSubject`, caches it, `require()` throws `UnauthorizedException`
- **AuthorizedUser** / **AuthSubject** — roles & permissions
- **PermissionsProviderInterface** — app bridges its schema to the framework
- Auth pipes: `RequireAuthPipe`, `RequirePermissionPipe`, `SessionAuthPipe`, `StartSessionPipe`
- Session-based login/logout flow
