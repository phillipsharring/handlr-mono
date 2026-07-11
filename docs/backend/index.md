# Backend Overview

> [!NOTE] Draft
> This page is a stub. Content coming.

`handlr-backend` is a lightweight PHP middleware-style framework built around the **Pipe + Handler** pattern. It separates HTTP concerns from business logic.

- **Pipe** — middleware that sees `Request`/`Response`. Auth, validation, CORS, CSRF.
- **Handler** — pure business logic. Receives typed `HandlerInput`, returns `HandlerResult`. No HTTP awareness.
- **HandlerInput** — validated input object. Identical for HTTP requests and event listeners.
- **HandlerResult** — structured result: `$this->result->ok($data)` or `$this->result->fail($errors)`.

Below the Pipe layer, everything is Handler/HandlerInput — whether it came from HTTP or an event dispatch.

## To cover

- The request lifecycle (Kernel → Router → Pipeline → Handler)
- Dependency injection & the container
- Where each abstraction lives and why
