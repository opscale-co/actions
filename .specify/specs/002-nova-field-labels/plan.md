# Plan: Translatable Nova field labels with per-parameter override

## Approach

Two touch points in `NovaActionAdapter`. `getActionFields()` forwards the
optional `label` key of each `parameters()` entry into `createNovaField()`,
which now takes a nullable `$label` and, when null, falls back to a new
`translateParameterLabel()` helper: the existing humanization
(`str_replace('_', ' ', ucfirst($name))`) passed through `__()`. Without a
matching translation `__()` echoes the key back, so serialization is
identical to today.

## Files

- `src/Adapters/NovaActionAdapter.php` — label forwarding, nullable
  `$label` parameter, `translateParameterLabel()` helper.
- `src/Action.php` — document the `label` key in the `parameters()`
  docblock (next to the existing `options` key) and in the array shape.
- `tests/Feature/NovaFieldLabelTest.php` — Pest Feature tests: translated
  fallback, explicit override wins, default unchanged, override on the
  options-driven Select path.
- `README.md` — new "Field labels" section under Usage.

## Trade-offs

- `__()` can return an array for group keys; the helper guards with
  `is_string()` and falls back to the humanized name, keeping the return
  type `string` without casts.
- The translation key is the humanized English name (not the raw snake_case
  parameter name) so hosts translate exactly what they see in the UI.
