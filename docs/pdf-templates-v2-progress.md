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
- [ ] Generate and review OpenAPI specification and generated client
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
- [ ] Remove old per-user profile storage and endpoint usage after the export path is switched
- [ ] Remove legacy *Layout* terminology after shared export replaces the legacy UI

## Stage 3: export

- [ ] Use a well-maintained TypeScript JSON rule engine; evaluate against `setlist` and `setlistentry`, including resolved score tags
- [ ] Make rule evaluation deterministic using stored explicit order
- [ ] Use first matching rule to apply optional backgroundColor and cellPadding, including break rows
- [ ] Refactor monolithic `pdf-exporter.ts` into `src/utils/pdfexport/`: data preparation, table rendering, full PDF rendering
- [ ] Built-in standard template remains a front-end-only default and is never stored
- [ ] Export selector displayed only with non-default templates available
- [ ] Default template dropdown in setlist details sidebar, editors only
- [ ] Readers can export with all shared templates; preserve column selection

## Safety notes

This is **not** a production-ready branch. Stage 1 intentionally retains the
legacy per-user profile UI/API until stages 2 and 3 replace them.
The schema migration and CRUD/FK behavior have been tested on an isolated Nextcloud 34 + SQLite instance. Automatic versioned app upgrades, MySQL/PostgreSQL, and the shared-template PDF export are still untested.
Do not deploy on `cloud.ntso.de` before local validation and backup.

## Frontend transition status (2026-11-18)

The new `PdfTemplatesEditor.vue` is intentionally a **stage-2 management UI**.
It is only mounted for editors in app settings and operates on the new shared OCS
endpoints. Conditions are stored as JSON Rules Engine-style ASTs, but selecting a
shared template for PDF export and evaluating its rules are **not implemented yet**.
The old per-user layout editor and export selector remain temporarily so existing
PDF export functionality is not lost. Do not advertise this branch as complete
or ready for production until stage 3 replaces that path.

No frontend-specific tests have been added, per the maintainer's request in #156.
Run `npm run lint` and `npm run build` before testing the editor in the disposable
Nextcloud instance; CSS/TypeScript checks have not yet been run on these commits.
