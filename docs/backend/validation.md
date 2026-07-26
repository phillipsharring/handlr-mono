# Validation

The `Validator` (`src/Validation/Validator.php`) checks and sanitizes raw input
against a rule set, then hands your handler a clean, typed shape. It runs in the pipe
layer, before the handler — so a handler never sees unvalidated data.

## Rule syntax

Rules are strings. Arguments are separated with `|` and `,`:

```php
$rules = [
    'email'    => 'required|email',
    'name'     => 'required|string|max:120',
    'age'      => 'nullable|int|min:0',
    'role'     => 'in|admin,editor,viewer',
    'password' => 'required|string|confirmed',   // checks password_confirmation
];
```

> [!IMPORTANT] `|` separates args, not `:`
> This is **not** Laravel. Rule arguments use the pipe: `in|a,b,c`, `max:120` uses a
> colon only for the single-value form. When in doubt, `rule|arg1,arg2`.

## Running it

```php
$validator->validate($data, $rules);   // bool
$validator->isValid();                  // bool
$validator->errors();                   // ['field' => ['message', ...]]
$validator->sanitized('email');         // the cleaned value
$validator->sanitized();                // all cleaned values
```

After a value passes its rule it is **sanitized** automatically — unless the rule is
one where sanitizing makes no sense (`required`, `confirmed`, `date`, `array`, `in`,
`json`). So `sanitized()` gives you trimmed strings, cast ints, normalized emails,
etc.

## `nullable` and defaults

- `nullable` — the field may be absent or `null`; other rules are skipped when it is.
- `default|<value>` — supply a fallback, cast to the right type (`int`, `float`,
  `bool`, `string`, `array`).

```php
'per_page' => 'nullable|int|default|25',
```

## Built-in rules

| Rule | Checks |
|---|---|
| `required` | present and non-empty |
| `nullable` | allows absent / null |
| `string` (`min`/`max`) | a string, optional length bounds |
| `int` / `float` (`min`/`max`) | numeric, optional bounds |
| `bool` | accepts `true/false/yes/no/1/0/y/n` |
| `array` | an array (or a JSON string) |
| `json` | valid JSON |
| `email` / `url` | format |
| `date` (`format`) | a date in `format` (default `Y-m-d`) |
| `in` | one of `in\|a,b,c` |
| `min` | numeric minimum |
| `confirmed` | matches `{field}_confirmation` |
| `uuid` / `uuid7` | a UUID (v7) |
| `exists` / `unique` | DB-backed presence / absence |

## In a HandlerInput

Input classes use the `ValidatesRules` trait to validate themselves:

```php
final class CreateChecklistInput implements HandlerInput
{
    use ValidatesRules;

    public string  $name;
    public ?string $user_id;

    public function __construct(array $body = [], private ?Validator $validator = null)
    {
        $this->errors = $this->runValidation([
            'name'    => 'required|string|max:120',
            'user_id' => 'required|uuid',
        ]);
        $this->name    = $this->sanitized('name');
        $this->user_id = $this->sanitized('user_id');
    }
}
```

`ValidatesRules` also gives you `has(string $field)` — useful for **partial updates**
where you only validate and apply the fields that were actually sent.

A pipe usually builds this via `ValidatedInputFactory` (which merges route params +
JSON body + server-set fields and returns `[$input, $errors]`); if `$errors` is
non-empty it returns a `422` with `Presenter::validationError($message, $errors)`.
See [Core Concepts](./concepts#handlerinput) and [API Responses](./api-responses).

## Output escaping

Validation cleans **input**. For **output**, use `OutputSanitizer` — a static utility
called at render time, not during validation:

```php
OutputSanitizer::html($value);   // HTML-escape
OutputSanitizer::url($value);    // URL context
OutputSanitizer::js($value);     // JS context
```

Keeping these separate is deliberate: sanitizing on input loses information; escaping
on output is context-specific and reversible.

## See also

- [Core Concepts](./concepts) — where validation sits in the request flow.
- [API Responses](./api-responses) — turning `errors()` into a `422` envelope.
- [Database](./database) — the `exists` / `unique` rules query through `Db`.
