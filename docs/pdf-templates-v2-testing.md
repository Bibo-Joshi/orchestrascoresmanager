# Validating shared setlist template backend and schema

Use a **new, disposable development checkout** of `blauhorn/orchestrascoresmanager` branch
`contrib/pdf-templates-v2`. Keep both productive Nextcloud installations untouched.

## PHP checks

On a machine with PHP, Composer and the project's required dependencies:

```bash
git clone --branch contrib/pdf-templates-v2 --single-branch \
  https://github.com/blauhorn/orchestrascoresmanager.git \
  ~/Projekte/osm-pdf-templates-v2
cd ~/Projekte/osm-pdf-templates-v2
bash scripts/check-pdf-template-backend.sh
```

This runs `composer lint`, `composer cs:check`, `composer test:unit`, and
`composer psalm`. The script will install Composer dependencies if missing.
For maximum compatibility, use the project's supported PHP version (8.2);
running PHP-CS-Fixer with PHP 8.5 may trigger warnings.

## Schema migration smoke test — **disposable Nextcloud only**

The migration must be tried with both an **existing** OSM database (upgrade)
and a **fresh** OSM database. Back up the disposable database first.

Point the Nextcloud development app directory at this branch. Then run,
inside the development container (replace `nextcloud-dev` with the actual name):

```bash
docker exec -u www-data nextcloud-dev php occ migrations:status orchestrascoresmanager
docker exec -u www-data nextcloud-dev php occ migrations:migrate orchestrascoresmanager
docker exec -u www-data nextcloud-dev php occ migrations:status orchestrascoresmanager
```

The migration class is `Version001003003Date20261118000000`, creates
`osm_setlist_templates`, and adds a nullable `default_template_id` FK to
`osm_setlists`. Nextcloud prefixes physical DB table names (typically `oc_`).

After running migration:

1. Check that both the new table and new nullable column exist in the
   disposable DB, including FK `ON DELETE SET NULL`.
2. Exercise template CRUD through the API with an editor and a non-editor.
3. Assign a template to a setlist, delete the template, and verify
   `default_template_id` becomes `NULL` while the setlist remains.
4. Run `migrations:migrate` again; it must not create duplicate objects.
5. Confirm all pre-existing setlists remain, including their score entries.

Do **not** run these migration commands against `cloud.ntso.de`. The branch
is not ready to deploy. A database dump and ideally a full disposable clone
are necessary to prove that existing user data survives the migration.

## Progress / limitations

The new unit tests cover schema construction/idempotency (using a mocked
schema), validation, CRUD service/controller methods, and editor policy.
The DB integration test above is a separate requirement; PHPUnit alone does
not prove that a real MySQL/PostgreSQL/SQLite migration succeeds. 
