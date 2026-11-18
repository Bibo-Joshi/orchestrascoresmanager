# PDF templates v2 — implementation notes

This branch is based on `contrib/pdf-layout-profiles-pr` and follows the requirements in
[Bibo-Joshi/orchestrascoresmanager#156](https://github.com/Bibo-Joshi/orchestrascoresmanager/issues/156#issuecomment-6930065311).

## Stage 1: shared template backend (integration-tested locally)

- [x] Shared `osm_setlist_templates` database table and optional `osm_setlists.default_template_id` foreign key (`ON DELETE SET NULL`)
- [x] `SetlistTemplateMapper`, validation service, policy and CRUD service
- [x] OCS API `GET/POST /setlist-templates` and `PATCH/DELETE /setlist-templates/{id}`
- [x] Use existing editor permissions for writes; authenticated users may read
- [x] Setlist response, create, patch and clone support optional `defaultTemplateId`
- [x] Initial unit tests for validation, policy, CRUD controller/service and DB mapping
- [x] Remove old frontend-only tests as requested
- [x] Run PHP-CS-Fixer, Psalm (PHP 8.2), and PHPUnit locally (515 tests, 2259 assertions)
- [x] Generate and review OpenAPI specifications and TypeScript client, including the setlist GET, POST, PATCH and clone contracts
- [x] Verify real SQLite migration, FK ON DELETE SET NULL, and template CRUD/setlist association via OCS API on disposable Nextcloud 34
- [ ] Verify automatic app upgrade and migration on a fresh Nextcloud installation (manual migration already succeeded on an existing disposable instance)
- [ ] Decide one-time migration of previously saved per-user profiles into shared templates

## Stage 2: application settings UI

- [x] Editor-only shared template management section in the existing app settings dialog (reads existing 'editable' initial state)
- [x] Template overview with name, description, Edit/Delete confirmation and full-width New Template button
- [x] Modal for creation/editing, with font size and spacing fields
- [x] Structured AND/OR/XOR tag rule builder and break-row rule with explicit priority; unknown AST preserved read-only
- [x] MDI arrow icons added to `src/icons`; existing delete icon reused
- [x] Add typed shared template API client for GET/POST/PATCH/DELETE
- [x] Replace the legacy profile UI and export integration with shared templates

## Stage 3: export

- [x] Evaluate JSON Rules Engine conditions against `setlist` and `setlistentry` using freshly loaded score tags
- [x] Apply the first matching rule by ascending `order`, including for break rows. An omitted override uses the template default.
- [x] Separate data preparation, rule evaluation, table rendering and document generation under `src/utils/pdfexport/`
- [x] Keep the built-in template in the frontend. Show the export dropdown when shared templates exist.
- [x] Let editors set a default in the setlist sidebar and let readers override that choice for a single export
- [x] Fall back to the built-in template when the setlist has no default or the selected shared template has been deleted
- [x] Preserve the existing PDF column selection and ordering controls

Use `setlistentry.tags` for the row's current score tags and `setlist.tags` for
the union of current tags across the setlist. Break rows expose
`setlistentry.breakDuration` in seconds and have an empty tag list.
The visual editor's XOR uses nested `any`, `all` and `not` groups. Rule paths
can address properties and array indices, for example `$.tags` or `$.tags[0]`.
Stored rules with invalid expressions can cause the affected export to fail.
The built-in template remains available in the export dialog.

## Verification and follow-up

- Browser checks on the disposable Nextcloud instance covered editor and reader exports, default selection in the sidebar, fresh score tags, nested AND/OR/XOR, rule priorities, break duration, row colors and padding, and interactive column selection and ordering.
- A standard-export comparison against the base commit produced identical PDF content apart from generated metadata for a setlist with a score, a break and a custom column order.
- Frontend lint, Stylelint and the production build passed in the development workspace. The default `vue-tsc` command is blocked by the existing `vue/ref-macros` entry. With that entry bypassed via `--types vite/client`, the remaining diagnostics also occur on the base branch.
- PHPUnit passed with 671 tests and 2,650 assertions when bootstrapped with the disposable Nextcloud 34 instance. Psalm and PHP-CS-Fixer checks of the changed PHP files passed.
- The legacy `/settings/user/pdf-layouts` endpoints and their per-user data remain available for a future migration decision. The application UI uses shared templates, and the legacy endpoints are excluded from OpenAPI generation.
- Automatic app upgrades and migration behavior on MySQL and PostgreSQL still need verification. Use the separate development and test instance for validation.

No frontend test files were added, per the maintainer's request in #156.
