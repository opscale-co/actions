# Plan: Forward wrapped action meta to Nova

## Approach

One addition to the `NovaActionDecorator` constructor, after name/uriKey
resolution: resolve `fromActionMethodOrProperty('getActionMeta', 'actionMeta',
[])` and, when non-empty, apply it via `withMeta()`. Fully generic — no
knowledge of any specific consumer package.

## Files

- `src/Decorators/NovaActionDecorator.php` — the constructor addition.
- `tests/Fixtures/MetaProbeAction.php` — minimal base fixture (no meta).
- `tests/Fixtures/CustomMetaAction.php` — declares `getActionMeta()`.
- `tests/Fixtures/PropertyMetaAction.php` — declares `$actionMeta`.
- `tests/Feature/NovaActionMetaTest.php` — Pest Feature tests asserting on
  `jsonSerialize()` for the three acceptance scenarios.
- `README.md` — new "Custom Nova Meta" section.

## Trade-offs

- A generic passthrough puts integration responsibility on the consumer (they
  declare the meta keys their Nova packages expect), keeping this package
  free of references to specific third-party packages.
