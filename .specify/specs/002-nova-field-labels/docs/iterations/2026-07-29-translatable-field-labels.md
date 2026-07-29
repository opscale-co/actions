# Iteration: Translatable Nova field labels with per-parameter override

> Date:   2026-07-29
> Module: actions (package)
> Branch: iter/nova-field-labels
> Type:   feat
> Status: shipped

## User story

As a package consumer running Nova in a non-English locale
I want the action modal field labels to go through the host translator, with
an optional explicit `label` per parameter
So that operators see the modal in their language instead of the raw
humanized parameter names.

## Acceptance criteria

- **Given** a host with `"Assessment types": "Tipos de prueba"` in its
  `lang/es.json` and locale `es`
  **When** the Nova field is built for parameter `assessment_types`
  **Then** the field label serializes as "Tipos de prueba"
- **Given** a parameter declaring `'label' => __('Interviewer')`
  **When** the Nova field is built
  **Then** the explicit label is used verbatim over the translated fallback
- **Given** no translation and no explicit `label`
  **When** the Nova field is built
  **Then** the label is the humanized name, identical to before
  (non-breaking)

## Out of scope

- CLI/MCP label changes — the CLI uses the signature and MCP uses
  `description`, both already author-controlled.
- Shipping translation files with this package.

## Notes

- Precedence: explicit `label` (verbatim) → `__(humanized name)` fallback.

## Spec-kit artifacts

- spec:  .specify/specs/002-nova-field-labels/spec.md
- plan:  .specify/specs/002-nova-field-labels/plan.md
- tasks: .specify/specs/002-nova-field-labels/tasks.md

## Commit

- SHA:     the commit that introduced this file (branch iter/nova-field-labels)
- Message: feat(nova): translatable field labels with per-parameter override
