# Spec: Forward wrapped action meta to Nova

## User story

As a package consumer building Nova admin panels
I want to declare arbitrary Nova meta on my action and have the Nova
decorator forward it automatically
So that third-party Nova packages that read serialized action meta can be
integrated without coupling this package to any of them.

## Acceptance criteria

- **Given** an action defining `getActionMeta(): array`
  **When** `NovaActionDecorator` wraps and serializes it
  **Then** that meta is applied via `withMeta()` and appears in the payload

- **Given** an action declaring an `$actionMeta` array property
  **When** the decorator wraps it
  **Then** the property meta is applied the same way

- **Given** an action with neither `getActionMeta()` nor `$actionMeta`
  **When** the decorator wraps it
  **Then** it serializes exactly as before (no extra meta — non-breaking)

## Out of scope

- Any reference to a specific third-party package (e.g. trait detection) —
  the passthrough must stay fully generic.
- Meta forwarding for the non-Nova adapters (CLI, API, MCP).

## Notes

- Follows the existing `fromActionMethodOrProperty` convention already used
  for title (`getActionTitle`/`actionTitle`) and uriKey.
- Release: minor — `feat(nova): forward wrapped action meta to Nova`.
