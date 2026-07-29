# Spec: Translatable Nova field labels with per-parameter override

## User story

As a package consumer running Nova in a non-English locale
I want the action modal field labels to go through the host translator, with
an optional explicit `label` per parameter
So that operators see the modal in their language instead of the raw
humanized parameter names ("Assessment types", "Counter salary", ...).

## Acceptance criteria

- **Given** a host with a JSON lang entry for the humanized name
  (`"Assessment types": "Tipos de prueba"` in `lang/es.json`, locale `es`)
  **When** the Nova field is built for parameter `assessment_types`
  **Then** the field label serializes as "Tipos de prueba"

- **Given** a parameter entry declaring an explicit `label`
  (`['name' => 'interviewer_id', 'label' => __('Interviewer'), ...]`)
  **When** the Nova field is built
  **Then** the explicit label is used verbatim, winning over the translated
  fallback

- **Given** no translation key and no explicit `label`
  **When** the Nova field is built
  **Then** the label is the humanized name, byte-identical to today
  (non-breaking)

## Out of scope

- CLI/MCP label changes — the CLI uses the signature and MCP uses
  `description`, both already author-controlled.
- Shipping translation files with this package — translation is the host's
  responsibility via its own lang JSON.

## Notes

- Precedence: explicit `label` (verbatim, author already translated it) →
  `__(humanized name)` fallback.
- Release: minor — `feat(nova): translatable field labels with
  per-parameter override`.
