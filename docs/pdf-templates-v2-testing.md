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

The script runs `composer lint` and `composer cs:check`, then only the
**Unit Tests** suite in a standalone checkout. Integration tests require an
initialized Nextcloud server and are only run in a Nextcloud checkout.

In a standalone checkout, the new query-builder and schema tests need Doctrine DBAL.
Install it into PHPUnit's isolated development dependency group:

```bash
cd vendor-bin/phpunit
composer require --dev 'doctrine/dbal:^4.0'
cd ../..
bash scripts/check-pdf-template-backend.sh
```

This changes `vendor-bin/phpunit/composer.json` and its lock file; these
test-only dependency changes should be committed once verified.
For maximum compatibility, use the project's supported PHP version (8.2);
running PHP-CS-Fixer with PHP 8.5 may trigger warnings.

## Psalm on hosts with PHP 8.5

Psalm 5.26.1 may crash with PHP 8.5 runtime deprecations. Rather than
changing the system PHP version, run the included isolated Docker runner:

```bash
bash scripts/psalm-php82-docker.sh
```

It builds `docker/php82-psalm/Dockerfile` from `debian:bookworm-slim` with
Debian's prebuilt PHP 8.2 CLI, `mbstring` and XML extensions, then binds the checkout
as `/work` under the invoking user's UID/GID. It does **not** modify the host
PHP installation or perform any database migrations. Docker caches the built
image for subsequent runs. The main `scripts/check-pdf-template-backend.sh`
also uses this runner automatically on PHP 8.5 when Docker is available.

## Schema migration smoke test — **disposable Nextcloud only**

The migration must be tried with both an **existing** OSM database (upgrade)
and a **fresh** OSM database. Back up the disposable database first.

Point the Nextcloud development app directory at this branch. In Nextcloud 34,
`migrations:status`, `migrations:execute`, and `migrations:migrate` are developer-only
commands and are only registered while system `debug` is enabled. On the **isolated
test container only**, temporarily enable debug mode (replace `nextcloud-dev` with
your test container's name), then execute just this migration:

```bash
docker exec -u www-data nextcloud-dev php occ config:system:get debug
docker exec -u www-data nextcloud-dev php occ config:system:set debug --type=boolean --value=true
docker exec -u www-data nextcloud-dev php occ migrations:status orchestrascoresmanager
docker exec -u www-data nextcloud-dev php occ migrations:execute orchestrascoresmanager 001003003Date20261118000000
docker exec -u www-data nextcloud-dev php occ migrations:status orchestrascoresmanager
docker exec -u www-data nextcloud-dev php occ config:system:set debug --type=boolean --value=false
```

Before enabling debug, **save the original value** and restore that value at the
end; if `debug` was previously absent, remove it again with `config:system:delete
debug` rather than assuming it was explicitly `false`. The example above uses
`false` as the usual default. Verify that the disposable volume backup exists
before running the migration. Never enable debug on the productive Nextcloud.

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
