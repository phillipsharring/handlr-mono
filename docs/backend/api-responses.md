# API Responses (Presenter)

Every JSON response an app returns shares one envelope, built by the **Presenter**
(`src/Api/Presenter.php`). A consistent shape is what lets the frontend's
[client-side templates](/frontend/htmx) and toast/error handling work the same way
on every endpoint.

## The envelope

```json
{
  "status": "success",
  "message": "Checklist created.",
  "data":  { "...": "..." },
  "meta":  { "page": 1, "total": 12 }
}
```

`status` is always one of `success`, `warning`, or `error`. `message`, `data`, and
`meta` appear when set.

## Building a response

The Presenter is fluent — set data, then call a terminal builder that returns the
array:

```php
// a collection
return $res->withJson(
    $this->presenter->withData($rows)->withMeta($pageMeta)->success()
);

// a single record
return $res->withJson(
    $this->presenter->fromRecord($checklist)->success('Saved.')
);
```

### Setting data

| Call | Effect |
|---|---|
| `withData(array $rows)` | a collection under `data` |
| `withSingleData(array $row)` | a single object under `data` |
| `fromRecord(Record $r)` | a single object from a record's `toArray()` |
| `withMeta(array $meta)` | the `meta` block (pagination, sort state, …) |
| `only([...])` | whitelist columns in the output |
| `without([...])` | blacklist columns (`only` wins if both set) |

Every item is run through the record's `toArray()` and the `only`/`without` filters,
so you never hand the client a raw row.

### Terminal builders

| Call | `status` | Use for |
|---|---|---|
| `success(?string $message = null)` | `success` | the happy path |
| `warning(?string $message = null)` | `warning` | succeeded, but flag something |
| `validationError(?string $message = null, array $fieldErrors = [])` | `error` | input failed validation — carries a per-field error map |
| `invariantError(?string $message = null)` | `error` | a business-rule failure with no field map (quota hit, illegal state) |

```php
// validation failure (422) — field-keyed
return $res->withJson(
    $this->presenter->validationError('Please fix the errors below.', $errors),
    Response::HTTP_UNPROCESSABLE_ENTITY
);

// business-rule failure — no field map
return $res->withJson(
    $this->presenter->invariantError('You have reached the free-plan limit of 10 lists.'),
    Response::HTTP_CONFLICT
);
```

The split between `validationError` and `invariantError` matters on the client: the
frontend renders a field-keyed `errors` map inline on the form, while a bare
`message` becomes a toast. See [HTMX Patterns](/frontend/htmx#form-errors).

## Errors thrown as exceptions

Not every error goes through the Presenter. Anything that throws a
`RequestException` subclass — `RecordNotFound` (404), `PolicyDenied` (403), a bad
JSON body (400) — is caught by the global `ErrorPipe` and rendered at its status,
with no per-handler code. That's why [resolution and policy](./authorization)
failures need no wiring in the handler: they short-circuit as exceptions and the
`ErrorPipe` formats them. Reach for the Presenter's error builders when the failure
is a *value your handler computed* (validation, an invariant); let exceptions handle
the *structural* failures (missing record, denied policy).

## See also

- [Core Concepts](./concepts) — `HandlerResult` and how a pipe maps it to a response.
- [Validation](./validation) — producing the field-error map for `validationError`.
- [Authorization](./authorization) — the exceptions the `ErrorPipe` renders.
