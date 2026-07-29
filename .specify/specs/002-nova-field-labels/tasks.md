# Tasks: Translatable Nova field labels with per-parameter override

- [x] T01 Forward the per-parameter `label` key from `getActionFields()` into `createNovaField()`
- [x] T02 Add nullable `$label` to `createNovaField()` with `translateParameterLabel()` fallback (`__(humanized)`)
- [x] T03 Document the `label` key in the `parameters()` docblock in `src/Action.php`
- [x] T04 Feature tests `tests/Feature/NovaFieldLabelTest.php`: translated fallback / explicit override / default unchanged / Select path
- [x] T05 Document "Field labels" in README.md
- [x] T06 Quality gate green (`composer run check`)
