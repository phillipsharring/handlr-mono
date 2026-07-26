# Events & Listeners

Events let one action trigger others without the acting code knowing who's listening.
A **Listener** is just a [Handler](./concepts#handler) registered on an event name —
same interface, typically returns `null`. That reuse is the whole design: business
logic written for an HTTP request runs unchanged as an event listener.

## EventManager

`EventManager` (`src/Core/EventManager.php`) is a synchronous dispatcher:

```php
$events->register('checklist.item.checked', $markCompletedListener);
$events->dispatch('checklist.item.checked', $input);   // runs every listener, in order
```

`dispatch()` (aliased `dispatchNow()`) runs each registered listener synchronously,
in registration order, passing the same input a handler would receive.

## Registering listeners

You rarely call `register()` by hand — declare listeners in a service provider's
`events()` map (see [Service Providers](./service-providers)):

```php
public function events(): array
{
    return [
        'user.signed_up'          => [CreateTutorialChecklistListener::class],
        'checklist.item.checked'  => [MarkChecklistCompletedListener::class],
        'checklist.item.skipped'  => [MarkChecklistCompletedListener::class],
        'checklist.item.unchecked'=> [UnmarkChecklistCompletedListener::class],
        'checklist.completed'     => [CheckHeadingCompletedListener::class],
    ];
}
```

Each value is a list of `Handler` class-strings — **0..N listeners per event**. The
registry wires them into the `EventManager` at boot. Note the same listener can be
registered on several events (here, checking *and* skipping both mark the checklist
complete).

## A listener

A listener is an ordinary handler that acts and returns nothing:

```php
final class MarkChecklistCompletedListener implements Handler
{
    public function __construct(private ChecklistsTable $table) {}

    public function handle(array|HandlerInput $input): ?HandlerResult
    {
        // ...mark the checklist complete when all items are done...
        return null;
    }
}
```

## Events vs direct calls

Use a direct handler call when it's **point-to-point** — "do this one thing, I need
the result." Use an event when it's **broadcast** — "this happened; whoever cares can
react." Signup dispatching `user.signed_up` shouldn't know that a tutorial checklist
gets created, a welcome email gets sent, and analytics get pinged. Adding a fourth
reaction is a new listener, not an edit to the signup handler.

## Transaction safety

Dispatch is synchronous and in-process, and `DbInterface` is a **shared singleton**,
so listeners run inside the same request (and, if you opened one, the same
transaction) as the code that dispatched. If a later listener throws and you're in a
transaction, you can roll the whole thing back. There's no async queue and no
cross-process boundary to reason about — listeners are just more code running on the
same connection.

## See also

- [Core Concepts](./concepts) — Handler and HandlerInput, reused here as listeners.
- [Service Providers](./service-providers) — the `events()` map that registers them.
