<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<SetlistEntry>
 */
class SetlistEntryMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_setlist_entries', SetlistEntry::class);
	}

	/**
	 * Find setlist entry by id
	 * @param int $id
	 * @return SetlistEntry
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod - May be used by external callers
	 */
	public function find(int $id): SetlistEntry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all setlist entries for a given setlist id
	 * @param int $setlistId
	 * @return SetlistEntry[]
	 * @throws Exception
	 */
	public function findBySetlistId(int $setlistId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('setlist_id', $qb->createNamedParameter($setlistId, IQueryBuilder::PARAM_INT)))
			->orderBy('index', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Batch update multiple setlist entries in a transaction.
	 * Handles uniqueness constraint by temporarily setting negative indices.
	 *
	 * @param SetlistEntry[] $entries Entries to update
	 * @return SetlistEntry[] Updated entries
	 * @throws Exception
	 */
	public function batchUpdate(array $entries): array {
		if (empty($entries)) {
			return [];
		}

		$this->db->beginTransaction();
		try {
			// Store target indices
			$targetIndices = [];
			foreach ($entries as $entry) {
				$targetIndices[$entry->getId()] = $entry->getIndex();
			}

			// First pass: Set temporary negative indices to avoid constraint violations
			foreach ($entries as $entry) {
				$entry->setIndex(-1 * $entry->getId());
				$this->update($entry);
			}

			// Second pass: Set final indices
			$result = [];
			foreach ($entries as $entry) {
				$entry->setIndex($targetIndices[$entry->getId()]);
				$updated = $this->update($entry);
				$result[] = $updated;
			}

			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Delete a setlist entry by id
	 * @param int $id
	 * @return void
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod - May be used by external callers
	 */
	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
