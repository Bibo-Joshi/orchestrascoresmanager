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
- [x] Verify first install and automatic versioned upgrades in the Stage 4 database matrix
- [x] Document an opt-in copy of per-user profiles into shared templates

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
Tag membership conditions on either tag list match whole names after trimming
and locale-aware lowercasing, as in the legacy exporter. If a shared template
cannot be applied, the export shows a warning naming it and downloads the
built-in standard PDF with the selected columns.

## Copy a legacy profile to a shared template

1. With the profile owner's approval, read their profiles in their own authenticated Nextcloud session with `GET /ocs/v2.php/apps/orchestrascoresmanager/settings/user/pdf-layouts?format=json` and the `OCS-APIRequest: true` header. Copy the selected object from `ocs.data.profiles` into a local backup. If the owner cannot edit setlists, they can give a setlist editor a copy of the profile they chose to share.
2. Build a shared template from that copy. Transfer the name, margin and cell padding. Map `titleFontSize` to `fontSizes.title`, `sectionFontSize` to `subtitle`, and `tableFontSize` to `scoreRow`, `breakRow` and `columnTitle`. Use 8 pt for the legend to match the legacy renderer.
3. Put a break rule first. Use an `all` group with two `setlistentry` conditions: `$.scoreId` with `equal` and `value: null`, and `$.breakDuration` with `notEqual` and `value: null`. Copy `breakColor` and `breakPadding` to its `backgroundColor` and `cellPadding`. Leave its label null to keep it out of the legend. The shared rule applies padding on all sides. The old renderer used separate horizontal padding and a minimum break-row height, so check the result visually.
4. In the remaining rules, preserve array order and use ascending `order` values. Copy each label and color to `label` and `backgroundColor`, with `cellPadding: null` to inherit the template default. Split the old expression on `|`, then split each group on `&`. Trim tokens, discard empty tokens and groups, and discard any rule with no groups left. Convert each token into a `setlistentry` tag membership condition. For example, `Male & Female | Duett` becomes the expression below.
5. As an editor, submit `{ "template": { "name": "...", "description": null, "fontSizes": { ... }, "margin": 8, "cellPadding": 1.5, "rules": [ ... ] } }` to `POST /ocs/v2.php/apps/orchestrascoresmanager/setlist-templates` using `OCS-APIRequest: true` and `Content-Type: application/json` in the editor’s authenticated session. Give each rule a unique ID within the template and let the server create the template ID. The API accepts up to 30 rules, so a profile with 30 tag rules needs review before adding a dedicated break rule. Record the returned template ID alongside the source profile ID to avoid duplicate imports. Preview it in a test export before assigning it as a setlist default.

```json
{
  "any": [
    {
      "all": [
        { "fact": "setlistentry", "path": "$.tags", "operator": "contains", "value": "Male" },
        { "fact": "setlistentry", "path": "$.tags", "operator": "contains", "value": "Female" }
      ]
    },
    { "fact": "setlistentry", "path": "$.tags", "operator": "contains", "value": "Duett" }
  ]
}
```

This procedure reads the legacy profile and creates a new shared template.
The source profile stays in the owner's settings. Use the shared template
editor for subsequent adjustments.

## Verification and follow-up

- Browser checks on the disposable Nextcloud instance covered editor and reader exports, default selection in the sidebar, fresh score tags, nested AND/OR/XOR, rule priorities, break duration, row colors and padding, and interactive column selection and ordering.
- A standard-export comparison against the base commit produced identical PDF content apart from generated metadata for a setlist with a score, a break and a custom column order.
- Frontend lint, Stylelint and the production build passed in the development workspace. The default `vue-tsc` command is blocked by the existing `vue/ref-macros` entry. With `--types vite/client`, the base commit `ab4ee42` has 21 diagnostics and the feature branch has 17. All 17 also occur on the base: missing AG Grid `Module` and an unused binding in `FullPageTable.vue`, nullability and optional calls, incompatible grid rows and callbacks, a string/number mismatch in score comments, and a duration formatter that can return `undefined`. The four baseline diagnostics in `ExportMenu.vue` and `SetlistSidebar.vue` were resolved in Stage 3. The project-wide configuration and unrelated diagnostics remain for follow-up.
- PHPUnit passed with 671 tests and 2,650 assertions when bootstrapped with the disposable Nextcloud 34 instance. Psalm and PHP-CS-Fixer checks of the changed PHP files passed.
- The legacy `/settings/user/pdf-layouts` endpoints and their per-user data remain available for a future migration decision. The application UI uses shared templates, and the legacy endpoints are excluded from OpenAPI generation.
- See Stage 4 for the tested upgrade matrix and release prerequisites.

No frontend test files were added, per the maintainer's request in #156.

## Stage 4: upgrade and database acceptance

The isolated test installations used Nextcloud 34.0.6.2. Upgrade fixtures
were created from `df7ac796`, the last commit before the template migration.
Before the upgrade, the migration history lacked `001003003Date20261118000000`
and neither the template table nor the default-template column was present.
With the updated sources still at `2.0.0`, `occ upgrade` reported “No upgrade
required” and left the migration pending. With `2.0.1` in the disposable
package, the same command applied and recorded the migration.

| Database | Fresh install | Upgrade with existing OSM records |
| --- | --- | --- |
| SQLite 3.46.1 | Passed | Passed after the SQLite correction. Compared the full fixture responses before and after. |
| MariaDB 11.4.13 | Passed | Passed from both a clean baseline and an unrecorded partial schema containing a template. Compared complete API snapshots on the clean upgrade and checked fixture values and the single pre-existing template on the partial upgrade. |
| PostgreSQL 16.16 | Passed | Passed. Verified the original fixture values after upgrading. |
| Oracle MySQL | Unexecuted | Reviewed the shared Doctrine path. MariaDB results do not verify Oracle MySQL. |

The test instances verified the template primary key, both indices,
the default-template foreign key and `ON DELETE SET NULL`, along with template
CRUD and default assignment via OCS. Existing setlists and entries, scores,
tags, collections, user preferences and legacy PDF profiles were checked.
The partial MariaDB case retained the pre-existing shared template without a
duplicate. The upgrade did not import any legacy profiles.

The first SQLite upgrade exposed cascading deletion of setlist entries
when Doctrine rebuilt the existing setlist table. The corrected SQLite
upgrade adds the reference and index directly before the schema diff. A
fresh pre-migration database confirmed that entries survive. A SQLite column
already present without its foreign key fails early, which avoids the unsafe
rebuild. MariaDB/MySQL and PostgreSQL use the portable schema path. A pending
migration can complete compatible partial schema elements. Nextcloud skips a
migration whose ID is already recorded, so a damaged installation needs a
separate repair on a restored copy.

Use the maintainer's next monotonically increasing release version for the
published package. If any target already has `2.0.0`, `2.0.1` is the smallest
stable bump demonstrated here. If `2.0.0` has yet to be released and all targets
have an older version, the maintainer can include this migration in
`2.0.0`. The branch's app version remains `2.0.0`.

PHP-CS-Fixer, Psalm, PHPUnit with integration tests on a clean test copy
(673 tests, 2,661 assertions), frontend lint, Stylelint and the production
build passed. For release, rehearse with the approved package and the
target's database engine and version, verify consistent backup restoration,
and use the [administrator upgrade and recovery procedure](pdf-templates-v2-upgrade.md).
No rollback rehearsal or Oracle MySQL runtime test was performed.

## Stage 5: MariaDB 10.6 and notebook test setup

Nextcloud `34.0.5-apache` (34.0.5.1) and MariaDB `10.6` (10.6.28) passed
fresh installation and a regular `occ upgrade` from OSM 2.0.0 to a disposable
2.0.1 package. The baseline used `df7ac796`. Before the upgrade neither
schema element nor the migration history row existed. The same-version
control left them absent. A synthetic MariaDB dump was taken before upgrading.

The upgrade preserved the complete API responses for the existing setlist
apart from the new null `defaultTemplateId`, its entries, scores and their tags,
collections, user preferences and the legacy PDF profile. Both runs verified
the migration history, template table, primary key, indices, foreign key,
template CRUD, default assignment and `ON DELETE SET NULL`. No production
data was used.

The Compose configuration in `dev/osm-local-test/` ran with substitute
bind paths on the isolated Cloud host (Docker 29.9.2, Compose 2.40.3).
The proxy responded over `127.0.0.1:8088`; MariaDB had no published port.
Bash syntax and Compose configuration validation passed. A mocked mount
check passed six failure scenarios and a successful launch, and a real
call on the Cloud host rejected the missing notebook NTFS mount. Lint,
Stylelint and the production build passed.

The real ntfs3/ext4 loop mount, reboot and unmount procedure and network
isolation on Docker 27.3.1 with Compose 2.29.7 require notebook verification.
A real NTSO restore remains a local operator task. See the
[German setup and restore guide](pdf-templates-v2-local-test-de.md).
