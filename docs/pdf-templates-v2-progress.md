# PDF templates v2 — implementation notes

This branch is based on `contrib/pdf-layout-profiles-pr` and follows the requirements in
[Bibo-Joshi/orchestrascoresmanager#156](https://github.com/Bibo-Joshi/orchestrascoresmanager/issues/156#issuecomment-6930065311).

## Stage 1: shared template backend (started)

- [x] Shared `osm_setlist_templates` database table and optional `osm_setlists.default_template_id` foreign key (`ON DELETE SET NULL`)
- [x] `SetlistTemplateMapper`, validation service, policy and CRUD service
- [x] OCS API `GET/POST /setlist-templates` and `PATCH/DELETE /setlist-templates/{id}`
- [x] Use existing editor permissions for writes; authenticated users may read
- [x] Setlist response, create, patch and clone support optional `defaultTemplateId`
- [x] Initial unit tests for validation, policy, CRUD controller/service and DB mapping
- [x] Remove old frontend-only tests as requested
- [ ] Run PHP lint, PHP-CS-Fixer, Psalm, PHPUnit and OpenAPI generation locally
- [ ] Extend DB interaction tests for insert/update/delete and FK deletion behavior
- [ ] Verify migrations on a fresh installation and on an upgraded instance
- [ ] Decide one-time migration of previously saved per-user profiles into shared templates

## Stage 2: application settings UI

- [ ] Editor-only template management in the existing app settings dialog
- [ ] Template overview with name, description, Edit/Delete confirmation and full-width New Template button
- [ ] Modal for creation/editing, with appropriate font size/spacing fields
- [ ] Structured AND/OR/XOR rule editor, rows added on demand, no free-form expressions
- [ ] MDI arrow icons placed in `src/icons`; existing delete icon reused
- [ ] Read shared API and remove old per-user profile storage and endpoint usage
- [ ] Consistent wording: *Template* throughout code, API and UI

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
The database migration is not yet tested against a real Nextcloud instance.
Do not deploy on `cloud.ntso.de` before local validation and backup.
