# Events & Listeners

> [!NOTE] Draft
> This page is a stub. Content coming.

## To cover

- **EventManager** — `register(eventName, handler)` / `dispatch(eventName, input)`; synchronous
- A Listener is just a Handler registered on an event name (0..N per event)
- Events vs direct handler calls (broadcast vs point-to-point)
- Registering listeners via a service provider's `events()` map
- Transaction safety across listener chains (shared `DbInterface` singleton)
