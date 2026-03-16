<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Server;
use OCP\ServerVersion;

/**
 * Migration to create setlists and setlist_entries tables.
 *
 * Tables created:
 * - osm_setlists: Main setlists table
 * - osm_setlist_entries: Entries in a setlist (scores and breaks)
 *
 * Constraints:
 * - Foreign key setlist_id -> osm_setlists.id (CASCADE on delete)
 * - Foreign key score_id -> osm_scores.id (RESTRICT on delete)
 * - Foreign key folder_collection_version_id -> osm_fc_versions.id (RESTRICT on delete)
 * - Unique constraint on (setlist_id, index) for entry ordering
 *
 * @psalm-suppress UnusedClass - Migration class discovered by Nextcloud framework
 */
class Version000006000Date20251221000000 extends SimpleMigrationStep {

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		// Detect Nextcloud version for boolean column handling
		// NC33+ requires 'notnull' => true for boolean columns, NC32 and older require 'notnull' => false
		// See https://github.com/nextcloud/server/pull/55156
		$serverVersion = Server::get(ServerVersion::class);
		$majorVersion = $serverVersion->getMajorVersion();
		$booleanNotNull = $majorVersion >= 33;

		// Create osm_setlists table
		if (!$schema->hasTable('osm_setlists')) {
			$table = $schema->createTable('osm_setlists');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('start_date_time', Types::DATETIME_IMMUTABLE, [
				'notnull' => false,
			]);
			$table->addColumn('duration', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('default_moderation_duration', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('folder_collection_version_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('is_draft', Types::BOOLEAN, [
				'notnull' => $booleanNotNull,
				'default' => false,
			]);
			$table->addColumn('is_published', Types::BOOLEAN, [
				'notnull' => $booleanNotNull,
				'default' => false,
			]);
			$table->setPrimaryKey(['id']);

			// Add foreign key to folder_collection_versions (RESTRICT to prevent deleting a version that's referenced)
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_fc_versions'),
				['folder_collection_version_id'],
				['id'],
				['onDelete' => 'RESTRICT'],
				'fk_osm_setlists_fcv'
			);
			$table->addIndex(['folder_collection_version_id'], 'idx_osm_setlists_fcv_id');
			$table->addIndex(['start_date_time'], 'idx_osm_setlists_start');
		}

		// Create osm_setlist_entries table
		if (!$schema->hasTable('osm_setlist_entries')) {
			$table = $schema->createTable('osm_setlist_entries');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('setlist_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('index', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('comment', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('moderation_duration', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('break_duration', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('score_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);

			// Add foreign key to setlists (CASCADE to auto-delete entries when setlist is deleted)
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_setlists'),
				['setlist_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_osm_setlist_entries_sl'
			);

			// Add foreign key to scores (RESTRICT to prevent deleting a score that's in a setlist)
			$table->addForeignKeyConstraint(
				$schema->getTable('osm_scores'),
				['score_id'],
				['id'],
				['onDelete' => 'RESTRICT'],
				'fk_osm_setlist_entries_sc'
			);

			// Unique constraint: index must be unique per setlist
			$table->addUniqueIndex(['setlist_id', 'index'], 'idx_osm_se_uniq_idx');
			$table->addIndex(['setlist_id'], 'idx_osm_se_sl_id');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
