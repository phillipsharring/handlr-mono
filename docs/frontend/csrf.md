# CSRF

> [!NOTE] Draft
> This page is a stub. Content coming.

## To cover

- Token-in-header strategy: session token mirrored to a JS-readable `XSRF-TOKEN` cookie
- Frontend reads the cookie, sends `X-CSRF-Token`
- Global `fetch()` interceptor + HTMX hook
- Token rotation after successful POST/PATCH/DELETE
- 403 handling and fresh-token recovery
- Backend pipes: `EnsureCsrfTokenPipe`, `VerifyCsrfTokenPipe`, `VerifyOriginPipe`
