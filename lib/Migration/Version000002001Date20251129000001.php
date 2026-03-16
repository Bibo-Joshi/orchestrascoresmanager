<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Migration;

use Closure;
use OCA\OrchestraScoresManager\Utility\DateTimeHelper;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration to add folder collection versioning support - Step 2.
 *
 * This migration:
 * 1. Creates initial versions for existing folder collections
 * 2. Migrates existing score/scorebook links to use the new version
 * 3. Updates active_version_id on folder collections
 * 4. Adds foreign key constraints and removes old folder_collection_id column
 *
 * @psalm-suppress UnusedClass - Migration class discovered by NextCloud framework
 */
class Version000002001Date20251129000001 extends SimpleMigrationStep {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Create initial versions for all existing folder collections
		$today = DateTimeHelper::today();
		$todayStr = $today->format('Y-m-d');

		// Get all existing folder collections
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('osm_folder_collections');
		$result = $qb->executeQuery();
		/** @var array<int, array{id: int|string}> $folderCollections */
		$folderCollections = $result->fetchAll();
		$result->closeCursor();

		foreach ($folderCollections as $fc) {
			$fcId = (int)$fc['id'];

			// Create a version for this folder collection
			$insertQb = $this->db->getQueryBuilder();
			$insertQb->insert('osm_fc_versions')
				->values([
					'folder_collection_id' => $insertQb->createNamedParameter($fcId, IQueryBuilder::PARAM_INT),
					'valid_from' => $insertQb->createNamedParameter($todayStr, IQueryBuilder::PARAM_STR),
					'valid_to' => $insertQb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
				]);
			$insertQb->executeStatement();
			/** @psalm-suppress DeprecatedMethod - Required for migration compatibility */
			$versionId = $this->db->lastInsertId('osm_fc_versions');

			// Update all existing links to use this version
			$updateQb = $this->db->getQueryBuilder();
			$updateQb->update('osm_scores_fc')
				->set('folder_collection_version_id', $updateQb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT))
				->where($updateQb->expr()->eq('folder_collection_id', $updateQb->createNamedParameter($fcId, IQueryBuilder::PARAM_INT)));
			$updateQb->executeStatement();

			// Update folder collection with active version
			$updateFcQb = $this->db->getQueryBuilder();
			$updateFcQb->update('osm_folder_collections')
				->set('active_version_id', $updateFcQb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT))
				->where($updateFcQb->expr()->eq('id', $updateFcQb->createNamedParameter($fcId, IQueryBuilder::PARAM_INT)));
			$updateFcQb->executeStatement();
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$scoresFcTable = $schema->getTable('osm_scores_fc');

		// IMPORTANT: Drop foreign keys FIRST before dropping indexes
		// MySQL/MariaDB may use indexes to enforce foreign keys, so FKs must be dropped first

		// Drop the foreign key that references osm_folder_collections by its known name
		// This FK was created in Version000001000Date20251127000000
		if ($scoresFcTable->hasForeignKey('fk_osm_scores_fc_fc')) {
			$scoresFcTable->removeForeignKey('fk_osm_scores_fc_fc');
		}

		// Now drop old indexes that reference folder_collection_id
		// These indexes are: idx_osm_scores_fc_fc_id, idx_osm_scores_fc_uniq_sc, idx_osm_sfc_uniq_sb, idx_osm_scores_fc_uniq
		$indexesToDrop = [
			'idx_osm_scores_fc_fc_id',
			'idx_osm_scores_fc_uniq_sc',
			'idx_osm_sfc_uniq_sb',
			'idx_osm_scores_fc_uniq',
		];
		foreach ($indexesToDrop as $indexName) {
			if ($scoresFcTable->hasIndex($indexName)) {
				$scoresFcTable->dropIndex($indexName);
			}
		}

		// Drop folder_collection_id column (data has been migrated)
		if ($scoresFcTable->hasColumn('folder_collection_id')) {
			$scoresFcTable->dropColumn('folder_collection_id');
		}

		// Add foreign key for folder_collection_version_id
		$scoresFcTable->addForeignKeyConstraint(
			$schema->getTable('osm_fc_versions'),
			['folder_collection_version_id'],
			['id'],
			['onDelete' => 'CASCADE'],
			'fk_osm_scores_fc_fcv'
		);

		// Add index for folder_collection_version_id
		$scoresFcTable->addIndex(['folder_collection_version_id'], 'idx_osm_scores_fc_fcv_id');

		// Add unique constraints for the new version-based structure
		// A score/scorebook can only appear once per version
		$scoresFcTable->addUniqueIndex(['folder_collection_version_id', 'score_id'], 'idx_osm_sfc_uniq_ver_sc');
		$scoresFcTable->addUniqueIndex(['folder_collection_version_id', 'score_book_id'], 'idx_osm_sfc_uniq_ver_sb');
		$scoresFcTable->addUniqueIndex(['folder_collection_version_id', 'index'], 'idx_osm_sfc_uniq_ver_idx');

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
