# Iteration: Forward wrapped action meta to Nova

> Date:   2026-07-28
> Module: actions (package)
> Branch: iter/nova-action-meta
> Type:   feat
> Status: shipped

## User story

As a package consumer building Nova admin panels
I want to declare arbitrary Nova meta on my action and have the Nova
decorator forward it automatically
So that third-party Nova packages that read serialized action meta can be
integrated without coupling this package to any of them.

## Acceptance criteria

- **Given** an action defining `getActionMeta(): array`
  **When** `NovaActionDecorator` serializes it
  **Then** the meta appears in the serialized payload
- **Given** an action declaring an `$actionMeta` array property
  **When** the decorator wraps it
  **Then** the property meta is applied the same way
- **Given** an action with neither
  **When** the decorator wraps it
  **Then** serialization is unchanged (non-breaking)

## Out of scope

- References to specific third-party packages (e.g. trait detection) — the
  passthrough is fully generic, per explicit user direction during the
  iteration.
- Meta forwarding for CLI/API/MCP adapters.

## Notes

- Follows the existing `fromActionMethodOrProperty` convention used for
  title and uriKey.

## Spec-kit artifacts

- spec:  .specify/specs/001-nova-action-meta/spec.md
- plan:  .specify/specs/001-nova-action-meta/plan.md
- tasks: .specify/specs/001-nova-action-meta/tasks.md

## Commit

- SHA:     the commit that introduced this file (branch iter/nova-action-meta)
- Message: feat(nova): forward wrapped action meta to Nova
