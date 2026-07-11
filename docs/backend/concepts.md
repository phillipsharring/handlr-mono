# Core Concepts

> [!NOTE] Draft
> This page is a stub. Content coming.

## To cover

- **Pipe** — `handle(Request, Response, array $args, callable $next): Response`; onion middleware, short-circuiting
- **Handler** — `handle(array|HandlerInput): ?HandlerResult`; one unit of business logic
- **HandlerInput** — typed input constructed from raw body + optional `Validator`
- **HandlerResult** — `success`/`data`/`errors`/`meta`; injected instance, never static
- **Listener** — a Handler registered on an event name (same interface, returns null)
- How the same Handler is reused across HTTP and events
