# Your First App

This walks the full path of one feature — a "widgets" list — from a route to a
handler to a rendered page. It assumes you scaffolded with
`composer create-project phillipsharring/handlr-app` (see [Installation](./installation)).

## Anatomy of a scaffolded app

```
backend/
├── app/                 # your code, grouped by feature
│   ├── routes.php       # declares the cross-cutting junctions
│   └── Widgets/         # a feature: provider, pipes, handlers, table, record
├── bootstrap.php        # locates the app root, boots the Kernel
├── config/              # app config + provider list
├── migrations/          # schema
└── public/              # the web entrypoint
```

Two files set the frame: `app/routes.php` declares the [junctions](/backend/routing#junctions)
(the shared CORS/session/CSRF/auth pipe stacks), and each feature's **service
provider** fills them.

## 1. Generate the pieces

Use the [makers](/backend/cli) to scaffold a record, a table, and an endpoint. These
are composer scripts — run them from `backend/`:

```bash
cd backend
composer run make:record WidgetRecord
composer run make:table WidgetsTable
composer run make:migration create_widgets_table
composer run make:scaffold ListWidgets          # Input + Handler + Pipe + Test
```

Fill in the migration and run it:

```bash
composer run migrate
```

## 2. The flow, end to end

A request moves through four small pieces (see [Core Concepts](/backend/concepts)):

**Pipe** — HTTP-facing. Builds validated input, calls the handler, maps the result:

```php
final class ListWidgets implements Pipe
{
    public function __construct(
        private ListWidgetsHandler $handler,
        private Presenter $presenter,
    ) {}

    public function handle(Request $req, Response $res, array $args, callable $next): Response
    {
        $result = $this->handler->handle($req->getRouteParams());
        return $res->withJson($this->presenter->withData($result->data)->success());
    }
}
```

**Handler** — pure logic, no HTTP:

```php
final class ListWidgetsHandler implements Handler
{
    public function __construct(private WidgetsTable $table, private HandlerResult $result) {}

    public function handle(array|HandlerInput $input): ?HandlerResult
    {
        $widgets = $this->table->findWhere([], [], [['created_at', 'DESC']]);
        return $this->result->ok(array_map(fn($w) => $w->toArray(), $widgets));
    }
}
```

**HandlerInput** validates the shape (for a write); **HandlerResult** carries the
outcome. The same handler could run as an [event listener](/backend/events) unchanged.

## 3. Register the route

In your feature's provider, attach the route to a junction:

```php
final class WidgetsServiceProvider extends ServiceProvider
{
    public function routes(Router $router): void
    {
        $router->intoJunction('api.authed')
            ->group('/widgets')
                ->get('', [ListWidgets::class])
                ->post('', [CreateWidget::class])
            ->end();
    }
}
```

Then add `WidgetsServiceProvider` to the provider list in `config/`. See
[Service Providers](/backend/service-providers).

## 4. Wire the frontend page

Create a page that renders the endpoint with a [client-side template](/frontend/htmx):

```html
<!-- frontend/content/pages/widgets.html -->
<layout name="app" title="Widgets" />

<h1>Widgets</h1>
<ul hx-get="/api/widgets" hx-trigger="auth-load, refresh"
    hx-target="this" hx-select="unset" hx-swap="innerHTML"
    handlebars-array-template="widget-tpl" data-requires-auth></ul>

<template id="widget-tpl">
  {{#each data}}
    <li data-id="{{id}}">{{name}}</li>
  {{/each}}
</template>
```

`data-requires-auth` + `hx-trigger="auth-load"` defers the fetch until the user is
known ([Auth State](/frontend/auth-state)); the `<template>` renders the JSON
[envelope](/backend/api-responses). Run both servers (`php -S` and `npm run dev`) and
open the page.

## 5. Run the tests

`make:scaffold` generated a test alongside the pipe. Run the suite:

```bash
composer run test        # from backend/ — Pest under the hood
```

## Where to go next

- [Authorization](/backend/authorization) — make `/widgets/{id}` load and authorize the
  record before the handler runs.
- [Validation](/backend/validation) — validate the create form's input.
- [Declarative Behavior](/frontend/declarative) — handle form results with attributes.
