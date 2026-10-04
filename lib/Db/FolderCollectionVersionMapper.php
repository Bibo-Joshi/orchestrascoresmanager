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
 * Mapper for FolderCollectionVersion entity.
 *
 * @extends QBMapper<FolderCollectionVersion>
 */
class FolderCollectionVersionMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_fc_versions', FolderCollectionVersion::class);
	}

	/**
	 * Find a folder collection version by its ID.
	 *
	 * @param int $id
	 * @return FolderCollectionVersion
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function find(int $id): FolderCollectionVersion {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Find all versions for a folder collection.
	 *
	 * @param int $folderCollectionId
	 * @return FolderCollectionVersion[]
	 * @throws Exception
	 */
	public function findAllForFolderCollection(int $folderCollectionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('folder_collection_id', $qb->createNamedParameter($folderCollectionId, IQueryBuilder::PARAM_INT)))
			->orderBy('valid_from', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Find the active version for a folder collection (where valid_to is null).
	 *
	 * @param int $folderCollectionId
	 * @return FolderCollectionVersion|null
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findActiveVersion(int $folderCollectionId): ?FolderCollectionVersion {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('folder_collection_id', $qb->createNamedParameter($folderCollectionId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('valid_to'));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
		// MultipleObjectsReturnedException is allowed to propagate up - this is an invalid state
	}

	/**
	 * Find the latest version for a folder collection.
	 *
	 * @param int $folderCollectionId
	 * @return FolderCollectionVersion
	 * @throws Exception
	 * @throws DoesNotExistException if no version exists for this folder collection
	 */
	public function findLatestVersion(int $folderCollectionId): FolderCollectionVersion {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('folder_collection_id', $qb->createNamedParameter($folderCollectionId, IQueryBuilder::PARAM_INT)))
			->orderBy('valid_from', 'DESC')
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	/**
	 * Check if a date range overlaps with any existing version for a folder collection.
	 *
	 * Both $validFrom and $validTo should be DATE values (normalized to midnight UTC).
	 *
	 * @param int $folderCollectionId
	 * @param \DateTimeImmutable $validFrom Start date (should be normalized to midnight UTC)
	 * @param \DateTimeImmutable|null $validTo End date (should be normalized to midnight UTC, null = open-ended)
	 * @param int|null $excludeVersionId Version ID to exclude (for updates)
	 * @return bool True if there is an overlap
	 * @throws Exception
	 */
	public function hasOverlappingVersion(int $folderCollectionId, \DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo, ?int $excludeVersionId = null): bool {
		$qb = $this->db->getQueryBuilder();

		// Get all versions for this folder collection
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('folder_collection_id', $qb->createNamedParameter($folderCollectionId, IQueryBuilder::PARAM_INT)));

		if ($excludeVersionId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeVersionId, IQueryBuilder::PARAM_INT)));
		}

		$versions = $this->findEntities($qb);

		foreach ($versions as $version) {
			$existingFrom = $version->getValidFrom();
			$existingTo = $version->getValidTo();

			// Check for overlap:
			// Two ranges [A_from, A_to] and [B_from, B_to] overlap if:
			// A_from <= B_to (or B_to is null) AND B_from <= A_to (or A_to is null)
			$newToIsNull = $validTo === null;
			$existingToIsNull = $existingTo === null;

			$condition1 = $existingToIsNull || $validFrom <= $existingTo;
			$condition2 = $newToIsNull || $existingFrom <= $validTo;

			if ($condition1 && $condition2) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete all versions for a folder collection.
	 *
	 * @param int $folderCollectionId
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod - Public API for potential use
	 */
	public function deleteAllForFolderCollection(int $folderCollectionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('folder_collection_id', $qb->createNamedParameter($folderCollectionId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
