# Upgrade and restore OSM with shared PDF templates

## Before the maintenance window

1. Use a release approved by the maintainer and compatible with the installed Nextcloud version. Read the OSM package version in `appinfo/info.xml` and run `occ config:app:get orchestrascoresmanager installed_version` on the current instance. Use the instance's usual PHP runtime and web-service account for each `occ` command.
2. For an installed app, deploy a package that Nextcloud considers newer than the installed version. Nextcloud 34 compares the package and installed versions before dispatching an app upgrade. Copying newer PHP files over an installed package at the same version leaves pending migrations unapplied. On first install, Nextcloud runs the app's migration chain without an existing installed version.
3. Rehearse against a copy of the production database using the same database engine and server version. Exercise the actual signed release package, including the PDF export. Verify database privileges for the app's normal Nextcloud connection, including table, index and foreign key DDL.
4. Schedule downtime for the entire Nextcloud instance. Put it in maintenance mode with `occ maintenance:mode --on` and stop background jobs and other writers. Take a consistent backup of the entire Nextcloud database, configuration, data directory, installed Nextcloud version and app files, including custom app directories. Use the database engine's supported backup tools and verify that the backup can be restored. Retain the former OSM release and record the installed versions, database prefix and backup time.

## Deploy and verify

Prefer the instance's normal Nextcloud app update mechanism for an approved App Store release. For a manually administered app directory, deploy the complete trusted release package and run `occ upgrade`. Reload or restart PHP workers if OPcache does not revalidate source files. Keep an external maintenance gate in place until the checks below pass, because `occ upgrade` can turn off Nextcloud maintenance mode on success.

- Check the exit status and output of the upgrade and the Nextcloud log. Confirm the installed version with `occ config:app:get orchestrascoresmanager installed_version`.
- Using read-only database access, verify the migration history. Replace `oc_` with the instance's actual table prefix.

  ```sql
  SELECT version FROM oc_migrations
  WHERE app = 'orchestrascoresmanager'
    AND version = '001003003Date20261118000000';
  ```

- Inspect the database catalog for the `osm_setlist_templates` primary key on `id` and index on `name`. Verify that `osm_setlists.default_template_id` is nullable and indexed, and references the template ID with `ON DELETE SET NULL`.
- Verify representative sets, entries, scores, tags, collections and user preferences. With a disposable setlist and template, create and edit the template, assign it as the default, export a PDF as a reader, then delete the disposable template and verify the setlist falls back to the built-in standard. The upgrade preserves existing per-user profiles. Import them only with the [opt-in copying procedure](pdf-templates-v2-progress.md#copy-a-legacy-profile-to-a-shared-template).
- Turn off maintenance mode and resume background jobs once the checks pass.

## Failure and restoration

Keep Nextcloud in maintenance mode, stop all writers and retain the failed instance's logs, migration history and schema for diagnosis. An interrupted migration may have applied some DDL, especially on MariaDB/MySQL. If the migration is pending, the Stage 4 version can add missing named indices and, on MariaDB/MySQL and PostgreSQL, a missing foreign key to compatible schema elements. The SQLite upgrade adds a new column with an inline reference to avoid rebuilding the setlist table. If SQLite already has the column without a foreign key, the migration stops before attempting a rebuild. Restore the pre-upgrade backup before retrying. A wrong foreign key, orphaned references or an incompatible column or table definition requires investigation on a restored copy. A recorded migration is skipped by Nextcloud on later upgrades. Treat a recorded migration with missing constraints as a separate repair case.

If the upgrade cannot be completed safely, restore the matching pre-upgrade database, Nextcloud configuration, data and app files as a single backup generation. Restoring the old Git revision or copying back the old app files alone leaves the database and its migration history at the newer state. Verify the restored app version and representative data before restoring service.
