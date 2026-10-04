<?php

declare(strict_types=1);

namespace OCA\OrchestraScoresManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for the score/scorebook to folder collection version links.
 *
 * @extends QBMapper<\OCP\AppFramework\Db\Entity>
 */
class ScoreFolderCollectionLinkMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod - Constructor used by DI container */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'osm_scores_fc', null);
	}

	/**
	 * Find all version IDs for a given score (direct membership only).
	 *
	 * @param int $scoreId
	 * @return array<array{versionId: int, index: int|null}> Array of version info with optional index
	 * @throws Exception
	 */
	public function findVersionsForScore(int $scoreId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('folder_collection_version_id', 'index')
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->isNull('score_book_id')
				)
			);
		/** @var array<int, array{folder_collection_version_id: int|string, index: int|string|null}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$result = [];
		foreach ($rows as $row) {
			$result[] = [
				'versionId' => (int)$row['folder_collection_version_id'],
				'index' => $row['index'] !== null ? (int)$row['index'] : null,
			];
		}
		return $result;
	}

	/**
	 * Find all version IDs for a given score book.
	 *
	 * @param int $scoreBookId
	 * @return array<array{versionId: int, index: int|null}> Array of version info with optional index
	 * @throws Exception
	 */
	public function findVersionsForScoreBook(int $scoreBookId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('folder_collection_version_id', 'index')
			->from($this->getTableName())
			->where($qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)));
		/** @var array<int, array{folder_collection_version_id: int|string, index: int|string|null}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$result = [];
		foreach ($rows as $row) {
			$result[] = [
				'versionId' => (int)$row['folder_collection_version_id'],
				'index' => $row['index'] !== null ? (int)$row['index'] : null,
			];
		}
		return $result;
	}

	/**
	 * Find all score IDs directly linked to a folder collection version (not via score books).
	 *
	 * @param int $versionId
	 * @return array<array{id: int, index: int|null}> Array of score info with optional index
	 * @throws Exception
	 */
	public function findScoresForVersion(int $versionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_id', 'index')
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->isNotNull('score_id')
				)
			);
		/** @var array<int, array{score_id: int|string, index: int|string|null}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$result = [];
		foreach ($rows as $row) {
			$result[] = [
				'id' => (int)$row['score_id'],
				'index' => $row['index'] !== null ? (int)$row['index'] : null,
			];
		}
		return $result;
	}

	/**
	 * Find all score book IDs linked to a folder collection version.
	 *
	 * @param int $versionId
	 * @return array<array{id: int, index: int|null}> Array of score book info with optional index
	 * @throws Exception
	 */
	public function findScoreBooksForVersion(int $versionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_book_id', 'index')
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->isNotNull('score_book_id')
				)
			);
		/** @var array<int, array{score_book_id: int|string, index: int|string|null}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$result = [];
		foreach ($rows as $row) {
			$result[] = [
				'id' => (int)$row['score_book_id'],
				'index' => $row['index'] !== null ? (int)$row['index'] : null,
			];
		}
		return $result;
	}

	/**
	 * Add a score to a folder collection version.
	 *
	 * @param int $scoreId
	 * @param int $versionId
	 * @param int|null $index
	 * @throws Exception
	 */
	public function addScoreToVersion(int $scoreId, int $versionId, ?int $index = null): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'score_id' => $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT),
				'folder_collection_version_id' => $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT),
				'index' => $qb->createNamedParameter($index, IQueryBuilder::PARAM_INT),
				'score_book_id' => $qb->createNamedParameter(null, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	/**
	 * Add a score book to a folder collection version.
	 *
	 * @param int $scoreBookId
	 * @param int $versionId
	 * @param int|null $index
	 * @throws Exception
	 */
	public function addScoreBookToVersion(int $scoreBookId, int $versionId, ?int $index = null): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'score_id' => $qb->createNamedParameter(null, IQueryBuilder::PARAM_INT),
				'folder_collection_version_id' => $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT),
				'index' => $qb->createNamedParameter($index, IQueryBuilder::PARAM_INT),
				'score_book_id' => $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	/**
	 * Remove a score from a folder collection version.
	 *
	 * @param int $scoreId
	 * @param int $versionId
	 * @throws Exception
	 */
	public function removeScoreFromVersion(int $scoreId, int $versionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT))
				)
			);
		$qb->executeStatement();
	}

	/**
	 * Remove a score book from a folder collection version.
	 *
	 * @param int $scoreBookId
	 * @param int $versionId
	 * @throws Exception
	 */
	public function removeScoreBookFromVersion(int $scoreBookId, int $versionId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT))
				)
			);
		$qb->executeStatement();
	}

	/**
	 * Count scores directly in a folder collection version (not via score books).
	 *
	 * @param int $versionId
	 * @return int
	 * @throws Exception
	 */
	public function countScoresInVersion(int $versionId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->isNotNull('score_id')
				)
			);
		return (int)$qb->executeQuery()->fetchOne();
	}

	/**
	 * Count score books in a folder collection version.
	 *
	 * @param int $versionId
	 * @return int
	 * @throws Exception
	 * @psalm-suppress PossiblyUnusedMethod - Public API for potential future use
	 */
	public function countScoreBooksInVersion(int $versionId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->isNotNull('score_book_id')
				)
			);
		return (int)$qb->executeQuery()->fetchOne();
	}

	/**
	 * Check if a score is directly in a folder collection version (not via score book).
	 *
	 * @param int $scoreId
	 * @param int $versionId
	 * @return bool
	 * @throws Exception
	 */
	public function isScoreDirectlyInVersion(int $scoreId, int $versionId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('score_id', $qb->createNamedParameter($scoreId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->isNull('score_book_id')
				)
			);
		return (int)$qb->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Check which of the given scores are directly in a folder collection version (not via score book).
	 *
	 * @param int[] $scoreIds
	 * @param int $versionId
	 * @return int[] Score IDs that are directly in the version
	 * @throws Exception
	 */
	public function findScoresDirectlyInVersion(array $scoreIds, int $versionId): array {
		$scoreIds = array_map('intval', $scoreIds);
		if (empty($scoreIds)) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_id')
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->in('score_id', $qb->createNamedParameter($scoreIds, IQueryBuilder::PARAM_INT_ARRAY)),
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->isNull('score_book_id')
				)
			);
		/** @var array<int, array{score_id: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		return array_map(fn ($row) => (int)$row['score_id'], $rows);
	}

	/**
	 * Check if a score book is in a folder collection version.
	 *
	 * @param int $scoreBookId
	 * @param int $versionId
	 * @return bool
	 * @throws Exception
	 */
	public function isScoreBookInVersion(int $scoreBookId, int $versionId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq('score_book_id', $qb->createNamedParameter($scoreBookId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($versionId, IQueryBuilder::PARAM_INT))
				)
			);
		return (int)$qb->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Copy all links from one version to another.
	 *
	 * @param int $sourceVersionId
	 * @param int $targetVersionId
	 * @throws Exception
	 */
	public function copyLinksToVersion(int $sourceVersionId, int $targetVersionId): void {
		// Get all links from source version
		$qb = $this->db->getQueryBuilder();
		$qb->select('score_id', 'score_book_id', 'index')
			->from($this->getTableName())
			->where($qb->expr()->eq('folder_collection_version_id', $qb->createNamedParameter($sourceVersionId, IQueryBuilder::PARAM_INT)));
		/** @var array<int, array{score_id: int|string|null, score_book_id: int|string|null, index: int|string|null}> $rows */
		$rows = $qb->executeQuery()->fetchAll();

		if (empty($rows)) {
			return;
		}

		// Use a transaction to ensure all inserts succeed or all fail
		$this->db->beginTransaction();
		try {
			foreach ($rows as $row) {
				$insertQb = $this->db->getQueryBuilder();
				$insertQb->insert($this->getTableName())
					->values([
						'score_id' => $insertQb->createNamedParameter($row['score_id'], $row['score_id'] !== null ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_NULL),
						'score_book_id' => $insertQb->createNamedParameter($row['score_book_id'], $row['score_book_id'] !== null ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_NULL),
						'folder_collection_version_id' => $insertQb->createNamedParameter($targetVersionId, IQueryBuilder::PARAM_INT),
						'index' => $insertQb->createNamedParameter($row['index'], $row['index'] !== null ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_NULL),
					]);
				$insertQb->executeStatement();
			}
			$this->db->commit();
		} catch (Exception $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
